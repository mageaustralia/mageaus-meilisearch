<?php

/**
 * Meilisearch_Search FAQ indexer.
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2026 Mageaustralia (https://mageaustralia.com.au)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License 3.0
 */
class Meilisearch_Search_Model_Indexer_Meilisearchfaqs extends Meilisearch_Search_Model_Indexer_Abstract
{
    public const EVENT_MATCH_RESULT_KEY = 'meilisearch_match_result';

    /** @var Meilisearch_Search_Model_Resource_Engine */
    protected $engine;

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    protected $_matchedEntities = [];

    public function __construct()
    {
        parent::__construct();
        $this->engine = new Meilisearch_Search_Model_Resource_Engine();
        $this->config = Mage::helper('meilisearch_search/config');
    }

    protected function _getResource()
    {
        return Mage::getResourceSingleton('catalogsearch/indexer_fulltext');
    }

    public function getName()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search FAQs');
    }

    public function getDescription()
    {
        $helper = Mage::helper('meilisearch_search');
        return $helper->__('Rebuild FAQs.') . ' ' . $helper->__($this->enableQueueMsg);
    }

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

    public function reindexAll()
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return $this;
        }

        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: configure Server URL and API Key in System > Configuration > Meilisearch Search.');
            return $this;
        }

        $this->engine->rebuildFaqs();

        return $this;
    }
}
