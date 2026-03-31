<?php

class Meilisearch_Search_Model_Indexer_Meilisearchqueuerunner extends Mage_Index_Model_Indexer_Abstract
{
    public const INDEXER_ID = 'meilisearch_queue_runner';
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_match_result';

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Model_Queue */
    protected $queue;

    public function __construct()
    {
        parent::__construct();
        $this->config = Mage::helper('meilisearch_search/config');
        $this->queue = Mage::getSingleton('meilisearch_search/queue');
    }

    protected $_matchedEntities = [];

    #[\Override]
    protected function _getResource()
    {
        return Mage::getResourceSingleton('catalogsearch/indexer_fulltext');
    }

    public function getName()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search Queue Runner');
    }

    #[\Override]
    public function getDescription()
    {
        return Mage::helper('meilisearch_search')->__('Process the queue if enabled. This allow to run jobs in the queue');
    }

    #[\Override]
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        return false;
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        return $this;
    }

    protected function _registerCatalogProductEvent(Mage_Index_Model_Event $event)
    {
        return $this;
    }

    protected function _registerCatalogCategoryEvent(Mage_Index_Model_Event $event)
    {
        return $this;
    }

    protected function _processEvent(Mage_Index_Model_Event $event) {}

    /**
     * Rebuild all index data.
     */
    #[\Override]
    public function reindexAll()
    {
        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');

            return $this;
        }

        $this->queue->runCron();

        return $this;
    }
}
