<?php

class Meilisearch_Search_Model_Indexer_Meilisearch extends Meilisearch_Search_Model_Indexer_Abstract
{
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_match_result';

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Helper_Logger */
    protected $logger;

    public static $product_categories = [];
    protected static $credential_error = false;

    public function __construct()
    {
        parent::__construct();

        $this->config = Mage::helper('meilisearch_search/config');
        $this->logger = Mage::helper('meilisearch_search/logger');
    }

    protected $_matchedEntities = [
        Mage_Catalog_Model_Product::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
        Mage_Catalog_Model_Resource_Eav_Attribute::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
        Mage_Core_Model_Store::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
        Mage_Core_Model_Store_Group::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
        Mage_Core_Model_Config_Data::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
        Mage_Catalog_Model_Convert_Adapter_Product::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
        ],
        Mage_Catalog_Model_Category::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
    ];

    public function getName()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search Products');
    }

    #[\Override]
    public function getDescription()
    {
        /** @var Meilisearch_Search_Helper_Data $helper */
        $helper = Mage::helper('meilisearch_search');
        $decription = $helper->__('Rebuild products.') . ' ' . $helper->__($this->enableQueueMsg);

        return $decription;
    }

    #[\Override]
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        /** @var Mage_Index_Model_Indexer $indexer */
        $indexer = Mage::getModel('index/indexer');
        $process = $indexer->getProcessByCode('meilisearch_search_indexer');

        $result = $process->getMode() !== Mage_Index_Model_Process::MODE_MANUAL;

        $result = $result && $event->getEntity() !== 'core_config_data';

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
            case Mage_Catalog_Model_Convert_Adapter_Product::ENTITY:
                $event->addNewData('meilisearch_reindex_all', true);
                break;
            case Mage_Core_Model_Store_Group::ENTITY:
                $event->addNewData('meilisearch_reindex_all', true);
                break;
            case Mage_CatalogInventory_Model_Stock_Item::ENTITY:
                if (false == $this->config->getShowOutOfStock()) {
                    $this->_registerCatalogInventoryStockItemEvent($event);
                }
                break;
        }
    }

    protected function _registerCatalogInventoryStockItemEvent(Mage_Index_Model_Event $event)
    {
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $object = $event->getDataObject();

            /** @var Mage_Catalog_Model_Abstract $modelProduct */
            $modelProduct = Mage::getModel('catalog/product');

            /** @var Mage_Catalog_Model_Product $product */
            $product = $modelProduct->load($object->getProductId());

            try {
                // In case of wrong credentials or overquota or block account. To avoid checkout process to fail

                $event->addNewData('catalogsearch_update_category_id', $product->getCategoryIds());
            } catch (\Exception $e) {
                $this->logger->log('Error while trying to update stock');
                $this->logger->log($e->getMessage());
                $this->logger->log($e->getTraceAsString());
            }
        }
    }

    protected function _registerCatalogProductEvent(Mage_Index_Model_Event $event)
    {
        switch ($event->getType()) {
            case Mage_Index_Model_Event::TYPE_SAVE:
                /** @var $product Mage_Catalog_Model_Product */
                $product = $event->getDataObject();

                $event->addNewData('catalogsearch_update_product_id', $product->getId());
                $event->addNewData('catalogsearch_update_category_id', $product->getCategoryIds());

                /* product_categories is filled in Observer::saveProduct */
                if (isset(static::$product_categories[$product->getId()])) {
                    $oldCategories = static::$product_categories[$product->getId()];
                    $newCategories = $product->getCategoryIds();

                    $diffCategories = array_merge(
                        array_diff($oldCategories, $newCategories),
                        array_diff($newCategories, $oldCategories),
                    );

                    $event->addNewData('catalogsearch_update_category_id', $diffCategories);
                }
                break;

            case Mage_Index_Model_Event::TYPE_DELETE:
                /** @var $product Mage_Catalog_Model_Product */
                $product = $event->getDataObject();

                $event->addNewData('catalogsearch_update_product_id', $product->getId());
                $event->addNewData('catalogsearch_update_category_id', $product->getCategoryIds());
                break;

            case Mage_Index_Model_Event::TYPE_MASS_ACTION:
                /** @var Varien_Object $actionObject */
                $actionObject = $event->getDataObject();

                $event->addNewData('catalogsearch_update_product_id', $actionObject->getProductIds());
                break;
        }

        return $this;
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return;
        }

        // If queue is active, let it handle credential validation later
        // This prevents false errors when credentials are properly configured
        if ($this->config->isQueueActive()) {
            // Queue will handle credential validation when processing
            // Continue without checking credentials here
        } else {
            // Only check credentials if direct indexing (non-queue mode)
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
                if (self::$credential_error === false) {
                    /** @var Mage_Adminhtml_Model_Session $session */
                    $session = Mage::getSingleton('adminhtml/session');
                    $session->addError('Meilisearch indexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

                    self::$credential_error = true;
                }

                return;
            }
        }

        $data = $event->getNewData();

        /*
         * Reindex all products
         */
        if (!empty($data['meilisearch_reindex_all'])) {
            $process = $event->getProcess();
            $process->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
        }

        if (!empty($data['catalogsearch_update_category_id'])) {
            $this->reindexSpecificCategories($data['catalogsearch_update_category_id']);
        }

        /*
         * Reindex products.
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
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

            $this->logger->log('ERROR Credentials not configured correctly (Server URL and/or API Key missing)');

            return $this;
        }

        $this->logger->start('PRODUCTS FULL REINDEX');
        $this->engine->rebuildProducts();
        $this->logger->stop('PRODUCTS FULL REINDEX');

        return $this;
    }
}
