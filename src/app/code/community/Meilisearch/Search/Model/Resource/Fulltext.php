<?php

class Meilisearch_Search_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /** Empty because we need it to do nothing (no mysql stuff), Indexing is handled by Model/Indexer/Meilisearch */
    protected $engine;

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Helper_Logger */
    protected $logger;

    public function __construct()
    {
        parent::__construct();

        $this->engine = new Meilisearch_Search_Model_Resource_Engine();
        $this->config = Mage::helper('meilisearch_search/config');
        $this->logger = Mage::helper('meilisearch_search/logger');
    }

    #[\Override]
    public function prepareResult($object, $queryText, $query)
    {
        $storeId = $query->getStoreId();
        if (!$this->config->getServerUrl($storeId) || !$this->config->getAPIKey($storeId) || $this->config->isEnabledFrontEnd($storeId) === false) {
            return parent::prepareResult($object, $queryText, $query);
        }

        return $this;
    }

    #[\Override]
    protected function _saveProductIndexes($storeId, $productIndexes)
    {
        if ($this->config->isEnabledBackend($storeId) === false) {
            return parent::_saveProductIndexes($storeId, $productIndexes);
        }

        return $this;
    }

    /**
     * Only used when reindexing everything. Otherwise Model/Indexer/Meilisearch will take care of the rest.
     *
     * @param int|null $storeId
     * @param array|null $productIds
     *
     * @return $this|Mage_CatalogSearch_Model_Resource_Fulltext
     */
    #[\Override]
    public function rebuildIndex($storeId = null, $productIds = null)
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return parent::rebuildIndex($storeId, $productIds);
        }

        if ($storeId !== null) {
            $this->reindex($storeId, $productIds);

            return $this;
        }

        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            $this->reindex($store->getId(), $productIds);
        }

        return $this;
    }

    private function reindex($storeId, $productIds)
    {
        if ($this->config->isEnabledBackend($storeId) === false) {
            return parent::rebuildIndex($storeId, $productIds);
        }

        return $this->reindexMeilisearch($storeId, $productIds);
    }

    private function reindexMeilisearch($storeId, $productIds)
    {
        if (!$this->config->getServerUrl($storeId) || !$this->config->getAPIKey($storeId)) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

            return;
        }

        /* Avoid Indexing twice */
        if (is_array($productIds) && $productIds > 0) {
            return;
        }

        $this->engine->rebuildProducts($storeId);
    }
}
