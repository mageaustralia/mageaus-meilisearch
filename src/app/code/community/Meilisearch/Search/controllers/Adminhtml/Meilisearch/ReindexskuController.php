<?php

class Meilisearch_Search_Adminhtml_Meilisearch_ReindexskuController extends Mage_Adminhtml_Controller_Action
{
    public const MAX_SKUS = 10;

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['reindexPost']);
        return parent::preDispatch();
    }

    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Meilisearch Search'))
            ->_title($this->__('Reindex SKU(s)'));

        $this->loadLayout();
        $this->_setActiveMenu('system/meilisearch/reindexsku');
        $this->renderLayout();
    }

    public function reindexPostAction()
    {
        if ($this->getRequest()->getParam('skus')) {
            $skus = array_filter(array_map(trim(...), preg_split("/(,|\r\n|\n|\r)/", (string) $this->getRequest()->getParam('skus'))));
            $session = Mage::getSingleton('adminhtml/session');
            $stores = Mage::app()->getStores();
            $config = Mage::helper('meilisearch_search/config');

            foreach ($stores as $storeId => $store) {
                if ($config->isEnabledBackend($storeId) === false) {
                    unset($stores[$storeId]);
                }
            }

            if (count($skus) > self::MAX_SKUS) {
                $session->addError($this->__(
                    'The maximal number of SKU(s) is %s. Could you please remove some SKU(s) to fit into the limit?',
                    self::MAX_SKUS,
                ));
                $this->_redirect('*/*/');

                return;
            }

            // Load the collection instead of loading every one individually
            $collection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('sku', ['in' => $skus])
                ->setFlag('require_stock_items', true);

            foreach ($skus as $sku) {
                try {
                    $product = $collection->getItemByColumnValue('sku', $sku);
                    if (!$product) {
                        throw new Meilisearch_Search_Model_Exception_ProductUnknownSkuException($this->__('Product with SKU "%s" was not found.', $sku));
                    }
                    $this->checkAndReindex($product, $stores);
                } catch (Meilisearch_Search_Model_Exception_ProductUnknownSkuException $e) {
                    $session->addError($e->getMessage());
                } catch (Meilisearch_Search_Model_Exception_ProductDeletedException $e) {
                    $session->addError(
                        $this->__('The product "%s" (%s) is deleted.', $e->getProduct()->getName(), $e->getProduct()->getSku()),
                    );
                } catch (Meilisearch_Search_Model_Exception_ProductOutOfStockException $e) {
                    $session->addError(
                        $this->__('The product "%s" (%s) is out of stock.', $e->getProduct()->getName(), $e->getProduct()->getSku()),
                    );
                } catch (Exception $e) {
                    $session->addError($e->getMessage());
                }
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     */
    protected function checkAndReindex($product, array $stores)
    {
        /** @var Meilisearch_Search_Helper_Entity_Producthelper $productHelper */
        $productHelper = Mage::helper('meilisearch_search/entity_producthelper');
        $session = Mage::getSingleton('adminhtml/session');

        $websites = Mage::app()->getWebsites();
        $groups = Mage::app()->getGroups();

        foreach ($stores as $storeId => $store) {
            if (!in_array($storeId, $product->getStoreIds())) {
                $session->addNotice($this->__(
                    'The product "%s" (%s) is not associated with store "%s \ %s \ %s".',
                    $product->getName(),
                    $product->getSku(),
                    $websites[$store->getWebsiteId()]->getName(),
                    $groups[$store->getGroupId()]->getName(),
                    $store->getName(),
                ));
                continue;
            }
            try {
                $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
                $productHelper->canProductBeReindexed($product, $storeId);
            } catch (Meilisearch_Search_Model_Exception_ProductDisabledException $e) {
                $session->addError(
                    $this->__(
                        'The product "%s" (%s) is disabled in store "%s \ %s \ %s".',
                        $e->getProduct()->getName(),
                        $e->getProduct()->getSku(),
                        $websites[$store->getWebsiteId()]->getName(),
                        $groups[$store->getGroupId()]->getName(),
                        $stores[$e->getStoreId()]->getName(),
                    ),
                );
                continue;
            } catch (Meilisearch_Search_Model_Exception_ProductNotVisibleException $e) {
                // If it's a simple product that is not visible, try to index its parent if it exists
                if ($e->getProduct()->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
                    $parentId = $productHelper->getParentProductIds([$e->getProduct()->getId()]);
                    if (isset($parentId[0])) {
                        $parentProduct = Mage::getModel('catalog/product')->load($parentId[0]);
                        $session->addError(
                            $this->__(
                                'The product "%s" (%s) is not visible but it has a parent product "%s" (%s) for store "%s \ %s \ %s".',
                                $e->getProduct()->getName(),
                                $e->getProduct()->getSku(),
                                $parentProduct->getName(),
                                $parentProduct->getSku(),
                                $websites[$store->getWebsiteId()]->getName(),
                                $groups[$store->getGroupId()]->getName(),
                                $stores[$e->getStoreId()]->getName(),
                            ),
                        );
                        $this->checkAndReindex($parentProduct, [$stores[$e->getStoreId()]]);
                        continue;
                    }
                } else {
                    $session->addError(
                        $this->__(
                            'The product "%s" (%s) is not visible in store "%s \ %s \ %s".',
                            $e->getProduct()->getName(),
                            $e->getProduct()->getSku(),
                            $websites[$store->getWebsiteId()]->getName(),
                            $groups[$store->getGroupId()]->getName(),
                            $stores[$e->getStoreId()]->getName(),
                        ),
                    );
                    continue;
                }
            }
            $productIds = [$product->getId()];
            $productIds = array_merge($productIds, $productHelper->getParentProductIds($productIds));

            Mage::helper('meilisearch_search')->rebuildStoreProductIndex($storeId, $productIds);

            $session->addSuccess(
                $this->__(
                    'The product "%s" (%s) has been reindexed for store "%s \ %s \ %s".',
                    $product->getName(),
                    $product->getSku(),
                    $websites[$store->getWebsiteId()]->getName(),
                    $groups[$store->getGroupId()]->getName(),
                    $store->getName(),
                ),
            );
        }
    }

    /**
     * Check ACL permissions.
     *
     * @return bool
     */
    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch_search/reindexsku');
    }
}
