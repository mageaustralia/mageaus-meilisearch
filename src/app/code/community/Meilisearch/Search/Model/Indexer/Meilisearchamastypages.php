<?php

class Meilisearch_Search_Model_Indexer_Meilisearchamastypages extends Meilisearch_Search_Model_Indexer_Abstract
{
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_match_result';

    /** @var Meilisearch_Search_Model_Resource_Engine */
    protected $engine;

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    public function __construct()
    {
        parent::__construct();

        $this->engine = new Meilisearch_Search_Model_Resource_Engine();
        $this->config = Mage::helper('meilisearch_search/config');
    }

    protected $_matchedEntities = [];

    #[\Override]
    protected function _getResource()
    {
        return Mage::getResourceSingleton('catalogsearch/indexer_fulltext');
    }

    public function getName()
    {
        if (!Mage::helper('core')->isModuleEnabled('Amasty_Shopby')) {
            return Mage::helper('meilisearch_search')->__('Meilisearch Search Amasty Pages (Module Not Installed)');
        }
        return Mage::helper('meilisearch_search')->__('Meilisearch Search Amasty Pages');
    }

    #[\Override]
    public function getDescription()
    {
        /** @var Meilisearch_Search_Helper_Data $helper */
        $helper = Mage::helper('meilisearch_search');

        if (!Mage::helper('core')->isModuleEnabled('Amasty_Shopby')) {
            return $helper->__('Amasty_Shopby module is not installed. This indexer will not run.');
        }

        $description = $helper->__('Rebuild Amasty Advanced Navigation pages.') . ' ' . $helper->__($this->enableQueueMsg);

        return $description;
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
        if ($this->config->isModuleOutputEnabled() === false) {
            return $this;
        }

        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials in System > Configuration > Meilisearch Search.');

            return $this;
        }

        // Check if Amasty Shopby module is enabled
        if (!Mage::helper('core')->isModuleEnabled('Amasty_Shopby')) {
            return $this;
        }

        $this->engine->rebuildAmastyPages();

        return $this;
    }
}
