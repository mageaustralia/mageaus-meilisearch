<?php

/**
 * MeiliSearch Management Controller
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Adminhtml_Meilisearch_ManageController extends Mage_Adminhtml_Controller_Action
{
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['reindexAll', 'clearIndexes', 'deleteIndex']);
        return parent::preDispatch();
    }

    /**
     * Index management page
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Meilisearch Search'))
            ->_title($this->__('Manage Indexes'));

        $this->loadLayout();
        $this->_setActiveMenu('system/meilisearch/manage');
        $this->renderLayout();
    }

    /**
     * Reindex all MeiliSearch indexes
     */
    public function reindexAllAction()
    {
        try {
            // Get all indexers
            $indexers = [
                'meilisearch_search_products',
                'meilisearch_search_categories',
                'meilisearch_search_pages',
                'meilisearch_search_suggestions',
            ];

            $processed = 0;
            foreach ($indexers as $indexerCode) {
                $process = Mage::getModel('index/indexer')->getProcessByCode($indexerCode);
                if ($process && $process->getId()) {
                    $process->reindexAll();
                    $processed++;
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Reindexed %d MeiliSearch indexes', $processed),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Error reindexing: %s', $e->getMessage()),
            );
        }

        $this->_redirectReferer();
    }

    /**
     * Clear all MeiliSearch indexes
     */
    public function clearIndexesAction()
    {
        try {
            $helper = Mage::helper('meilisearch_search/meilisearchhelper');
            $client = $helper->getClient();

            if (!$client) {
                throw new Exception('MeiliSearch client not initialized');
            }

            // Get all indexes
            $indexesResponse = $client->getIndexes();
            $indexes = $indexesResponse->toArray();

            $deleted = 0;
            $prefix = Mage::helper('meilisearch_search/config')->getIndexPrefix();

            foreach ($indexes['results'] as $index) {
                $indexUid = $index->getUid();
                // Only delete indexes with our prefix
                if (empty($prefix) || str_starts_with((string) $indexUid, (string) $prefix)) {
                    $client->deleteIndex($indexUid);
                    $deleted++;
                }
            }

            // Also clear the queue
            $queue = Mage::getModel('meilisearch_search/queue');
            $queue->clearQueue(true);

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Deleted %d MeiliSearch indexes and cleared the queue', $deleted),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Error clearing indexes: %s', $e->getMessage()),
            );
        }

        $this->_redirectReferer();
    }

    /**
     * Delete a single index via AJAX.
     * The index UID must start with the configured prefix to prevent deleting unrelated indexes.
     */
    public function deleteIndexAction()
    {
        $indexUid = (string) $this->getRequest()->getParam('index');
        $prefix   = (string) Mage::helper('meilisearch_search/config')->getIndexPrefix();

        if ($prefix !== '' && !str_starts_with($indexUid, $prefix)) {
            $this->getResponse()->setBody(json_encode([
                'success' => false,
                'message' => $this->__('Index UID does not match the configured prefix.'),
            ]));
            return;
        }

        try {
            $helper = Mage::helper('meilisearch_search/meilisearchhelper');
            $client = $helper->getClient();

            if (!$client) {
                throw new Exception('MeiliSearch client not initialized');
            }

            $client->deleteIndex($indexUid);

            $this->getResponse()->setBody(json_encode([
                'success' => true,
                'message' => $this->__('Index deleted successfully'),
            ]));
        } catch (Exception $e) {
            $this->getResponse()->setBody(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Check admin permissions
     */
    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch_search');
    }
}
