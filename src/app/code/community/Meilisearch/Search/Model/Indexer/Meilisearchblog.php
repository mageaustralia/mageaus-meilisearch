<?php

/**
 * Meilisearch indexer for Maho_Blog posts.
 *
 * Mirrors Meilisearchpages: a manual-only indexer (no event matching) that
 * the admin "Reindex Data" page or the index:reindex CLI calls. The actual
 * work is delegated to the engine's rebuildBlog() which enqueues per-store
 * jobs against the queue runner.
 *
 * Auto-disables (no-op reindex) when Maho_Blog isn't installed.
 */
class Meilisearch_Search_Model_Indexer_Meilisearchblog extends Meilisearch_Search_Model_Indexer_Abstract
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
        return Mage::helper('meilisearch_search')->__('Meilisearch Search Blog Posts');
    }

    #[\Override]
    public function getDescription()
    {
        $helper = Mage::helper('meilisearch_search');
        return $helper->__('Rebuild blog posts.') . ' ' . $helper->__($this->enableQueueMsg);
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

    protected function _processEvent(Mage_Index_Model_Event $event) {}

    #[\Override]
    public function reindexAll()
    {
        if ($this->config->isModuleOutputEnabled() === false) {
            return $this;
        }

        if (!$this->config->getServerUrl() || !$this->config->getAPIKey()) {
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError('Meilisearch reindexing failed: You need to configure your Meilisearch credentials (Server URL and API Key) in System > Configuration > Meilisearch Search.');
            return $this;
        }

        // Skip silently when the blog module isn't on the classpath - the
        // admin can leave this indexer in their list without it errorring.
        if (!Mage::helper('meilisearch_search/entity_bloghelper')->isBlogModuleEnabled()) {
            return $this;
        }

        $this->engine->rebuildBlog();

        return $this;
    }
}
