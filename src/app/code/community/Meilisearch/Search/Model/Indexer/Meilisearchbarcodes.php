<?php

class Meilisearch_Search_Model_Indexer_Meilisearchbarcodes extends Meilisearch_Search_Model_Indexer_Abstract
{
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_barcodes_match_result';

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Helper_Logger */
    protected $logger;

    /** @var Meilisearch_Search_Helper_Entity_Barcodeshelper */
    protected $barcodesHelper;

    public function __construct()
    {
        parent::__construct();

        $this->config = Mage::helper('meilisearch_search/config');
        $this->logger = Mage::helper('meilisearch_search/logger');
        $this->barcodesHelper = Mage::helper('meilisearch_search/entity_barcodeshelper');
    }

    protected $_matchedEntities = [
        Mage_Catalog_Model_Product::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
        Mage_CatalogInventory_Model_Stock_Item::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
    ];

    public function getName()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Barcode Scanner Index');
    }

    #[\Override]
    public function getDescription()
    {
        return Mage::helper('meilisearch_search')->__('Rebuild barcode scanner index for mobile app.');
    }

    #[\Override]
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        /** @var Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getModel('index/indexer');
        $process = $indexer->getProcessByCode('meilisearch_barcodes');

        $result = $process->getMode() !== Mage_Index_Model_Process::MODE_MANUAL;

        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, $result);

        return $result;
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, true);

        switch ($event->getEntity()) {
            case Mage_Catalog_Model_Product::ENTITY:
                $this->_registerCatalogProductEvent($event);
                break;
            case Mage_CatalogInventory_Model_Stock_Item::ENTITY:
                $this->_registerCatalogInventoryStockItemEvent($event);
                break;
        }
    }

    protected function _registerCatalogInventoryStockItemEvent(Mage_Index_Model_Event $event)
    {
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $object = $event->getDataObject();

            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->load($object->getProductId());

            if ($product->getId()) {
                $event->addNewData('barcode_update_product_id', $product->getId());
            }
        }
    }

    protected function _registerCatalogProductEvent(Mage_Index_Model_Event $event)
    {
        switch ($event->getType()) {
            case Mage_Index_Model_Event::TYPE_SAVE:
                /** @var $product Mage_Catalog_Model_Product */
                $product = $event->getDataObject();
                $event->addNewData('barcode_update_product_id', $product->getId());
                break;

            case Mage_Index_Model_Event::TYPE_DELETE:
                /** @var $product Mage_Catalog_Model_Product */
                $product = $event->getDataObject();
                $event->addNewData('barcode_delete_product_id', $product->getId());
                break;

            case Mage_Index_Model_Event::TYPE_MASS_ACTION:
                /** @var Varien_Object $actionObject */
                $actionObject = $event->getDataObject();
                $event->addNewData('barcode_update_product_id', $actionObject->getProductIds());
                break;
        }

        return $this;
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return;
        }

        // Check credentials
        $hasValidCredentials = false;
        foreach (Mage::app()->getStores() as $store) {
            if ($store->getIsActive() &&
                $this->config->getServerUrl($store->getId()) &&
                $this->config->getAPIKey($store->getId())) {
                $hasValidCredentials = true;
                break;
            }
        }

        if (!$hasValidCredentials) {
            return;
        }

        $data = $event->getNewData();

        // Update specific products in barcode index
        if (!empty($data['barcode_update_product_id'])) {
            $this->reindexSpecificProducts($data['barcode_update_product_id']);
        }

        // Delete products from barcode index
        if (!empty($data['barcode_delete_product_id'])) {
            $this->deleteSpecificProducts($data['barcode_delete_product_id']);
        }
    }

    /**
     * Reindex specific products in barcode index
     *
     * @param int|array $productIds
     */
    #[\Override]
    public function reindexSpecificProducts($productIds)
    {
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }

        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            if (!$this->config->isEnabledBackend($store->getId())) {
                continue;
            }

            try {
                $storeId = $store->getId();

                // Get products
                $products = $this->barcodesHelper->getProductCollectionQuery($storeId, $productIds, false);

                $records = [];
                foreach ($products as $product) {
                    $product->setStoreId($storeId);
                    $record = $this->barcodesHelper->getObject($product);
                    if (!empty($record)) {
                        $records[] = $record;
                    }
                }

                if (!empty($records)) {
                    $indexName = $this->barcodesHelper->getIndexName($storeId);
                    $meilisearchHelper = Mage::helper('meilisearch_search/meilisearchhelper');
                    $meilisearchHelper->addObjects($records, $indexName);
                }
            } catch (Exception $e) {
                $this->logger->log('Error reindexing barcodes for products: ' . implode(',', $productIds));
                $this->logger->log($e->getMessage());
            }
        }
    }

    /**
     * Delete specific products from barcode index
     *
     * @param int|array $productIds
     */
    public function deleteSpecificProducts($productIds)
    {
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }

        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            if (!$this->config->isEnabledBackend($store->getId())) {
                continue;
            }

            try {
                $storeId = $store->getId();
                $indexName = $this->barcodesHelper->getIndexName($storeId);
                $meilisearchHelper = Mage::helper('meilisearch_search/meilisearchhelper');
                $meilisearchHelper->deleteObjects($productIds, $indexName);
            } catch (Exception $e) {
                $this->logger->log('Error deleting barcodes for products: ' . implode(',', $productIds));
                $this->logger->log($e->getMessage());
            }
        }
    }

    /**
     * Rebuild all barcode index data
     */
    #[\Override]
    public function reindexAll()
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return $this;
        }

        // Check credentials for all active stores
        $hasValidCredentials = false;
        foreach (Mage::app()->getStores() as $store) {
            if ($store->getIsActive() &&
                $this->config->getServerUrl($store->getId()) &&
                $this->config->getAPIKey($store->getId())) {
                $hasValidCredentials = true;
                break;
            }
        }

        if (!$hasValidCredentials) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch barcode reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key).');
            $this->logger->log('ERROR Credentials not configured correctly for barcode index');
            return $this;
        }

        $this->logger->start('BARCODES FULL REINDEX');

        try {
            // Rebuild barcode index for all stores
            foreach (Mage::app()->getStores() as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                if (!$this->config->isEnabledBackend($store->getId())) {
                    continue;
                }

                $storeId = $store->getId();
                $this->logger->log("Rebuilding barcode index for store: {$storeId}");

                Mage::helper('meilisearch_search')->rebuildStoreBarcodesIndex($storeId);
            }

            $this->logger->log('Barcode index rebuild completed successfully');
        } catch (Exception $e) {
            $this->logger->log('ERROR during barcode reindex: ' . $e->getMessage());
            $this->logger->log($e->getTraceAsString());
        }

        $this->logger->stop('BARCODES FULL REINDEX');

        return $this;
    }
}
