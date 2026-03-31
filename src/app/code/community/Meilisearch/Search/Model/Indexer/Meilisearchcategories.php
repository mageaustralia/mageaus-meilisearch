<?php

class Meilisearch_Search_Model_Indexer_Meilisearchcategories extends Meilisearch_Search_Model_Indexer_Abstract
{
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_match_result';

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    protected static $credential_error = false;

    public function __construct()
    {
        parent::__construct();

        $this->config = Mage::helper('meilisearch_search/config');
    }

    protected $_matchedEntities = [
        Mage_Catalog_Model_Category::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
    ];

    public function getName()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search Categories');
    }

    #[\Override]
    public function getDescription()
    {
        /** @var Meilisearch_Search_Helper_Data $helper */
        $helper = Mage::helper('meilisearch_search');
        $decription = $helper->__('Rebuild categories.') . ' ' . $helper->__($this->enableQueueMsg);

        return $decription;
    }

    #[\Override]
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        $result = $event->getEntity() !== 'core_config_data';

        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, $result);

        return $result;
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, true);

        switch ($event->getEntity()) {
            case Mage_Catalog_Model_Category::ENTITY:
                $this->_registerCatalogCategoryEvent($event);
                break;
        }
    }

    protected function _registerCatalogCategoryEvent(Mage_Index_Model_Event $event)
    {
        switch ($event->getType()) {
            case Mage_Index_Model_Event::TYPE_SAVE:

                /** @var Mage_Catalog_Model_Category $category*/
                $category = $event->getDataObject();

                $productIds = [];
                if ($this->config->indexAllCategoryProductsOnCategoryUpdate()) {
                    $categories = array_merge([$category->getId()], $category->getAllChildren(true));

                    $collection = Mage::getResourceModel('catalog/product_collection');
                    $collection->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id = entity_id', null, 'left');
                    $collection->addAttributeToFilter('category_id', ['in' => $categories]);

                    $productIds = $collection->getAllIds();
                } elseif ($this->config->indexProductOnCategoryProductsUpdate()) {
                    $productIds = $category->getAffectedProductIds();
                }

                if (!$category->getData('is_active')) {
                    $categories = array_merge([$category->getId()], $category->getAllChildren(true));
                    $event->addNewData('catalogsearch_delete_category_id', $categories);

                    if ($productIds) {
                        $event->addNewData('catalogsearch_update_product_id', $productIds);
                    }
                } elseif ($productIds) {
                    $event->addNewData('catalogsearch_update_product_id', $productIds);
                    $event->addNewData('catalogsearch_update_category_id', [$category->getId()]);
                } elseif ($movedCategoryId = $category->getMovedCategoryId()) {
                    $event->addNewData('catalogsearch_update_category_id', [$movedCategoryId]);
                } else {
                    $event->addNewData('catalogsearch_update_category_id', [$category->getId()]);
                }

                break;

            case Mage_Index_Model_Event::TYPE_DELETE:

                /** @var $category Mage_Catalog_Model_Category */
                $category = $event->getDataObject();

                $event->addNewData('catalogsearch_delete_category_id', $category->getId());

                break;
        }

        return $this;
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return;
        }

        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            if (self::$credential_error === false) {
                /** @var Mage_Adminhtml_Model_Session $session */
                $session = Mage::getSingleton('adminhtml/session');
                $session->addError('Meilisearch indexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

                self::$credential_error = true;
            }

            return;
        }

        $data = $event->getNewData();

        /*
         * Reindex all products and all categories and update index settings
         */
        if (!empty($data['meilisearch_reindex_all'])) {
            $process = $event->getProcess();
            $process->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
        } else {
            /*
             * Clear indexer for the deleted category including all children categories and update index for the related products.
             */
            if (!empty($data['catalogsearch_delete_category_id'])) {
                $categoryIds = $data['catalogsearch_delete_category_id'];
                $this->engine->removeCategories(null, $categoryIds);
                /*
                 * Change indexer status as need to reindex related products to update the list of categories.
                 * It's low priority so no need to automatically reindex all related products after deleting each category.
                 * Do not reindex all if affected products are given or product count is not indexed.
                 */
                if (!isset($data['catalogsearch_update_product_id'])) {
                    $process = $event->getProcess();
                    $process->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
                }
            }
        }

        /*
         * Reindex categories.
         * Category products are tracked separately. The specified categories are active. See _registerCatalogCategoryEvent().
         */
        if (!empty($data['catalogsearch_update_category_id'])) {
            $this->reindexSpecificCategories($data['catalogsearch_update_category_id']);
        }

        /*
         * If we have added any new products to a category then we need to
         * update these products in Meilisearch indices.
         */
        if (!empty($data['catalogsearch_update_product_id'])) {
            $this->reindexSpecificProducts($data['catalogsearch_update_product_id']);
        }
    }

    /**
     * Rebuild all index data.
     */
    #[\Override]
    public function reindexAll()
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return $this;
        }

        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

            return $this;
        }

        $this->engine->rebuildCategories();

        return $this;
    }
}
