<?php

class Meilisearch_Search_Adminhtml_Meilisearch_IndexingqueueController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Controller predispatch method
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        $this->_checkQueueIsActivated();
        return $this;
    }

    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Meilisearch Search'))
            ->_title($this->__('Indexing Queue'));

        $this->loadLayout();
        $this->_setActiveMenu('system/meilisearch/indexing_queue');

        // Create the grid container block
        $this->_addContent($this->getLayout()->createBlock('meilisearch_search/adminhtml_queue'));

        $this->renderLayout();
    }

    public function viewAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Meilisearch Search'))
            ->_title($this->__('Indexing Queue'));

        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('meilisearch_search')->__('Indexing Queue Job ID is not set.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        $job = Mage::getModel('meilisearch_search/job')->load($id);
        if (!$job->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('meilisearch_search')->__('This indexing queue job no longer exists.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('meilisearch_current_job', $job);

        $this->loadLayout();
        $this->_setActiveMenu('system/meilisearch/indexing_queue');
        $this->renderLayout();
    }

    public function clearAction()
    {
        try {
            /** @var Meilisearch_Search_Model_Queue $queue */
            $queue = Mage::getModel('meilisearch_search/queue');
            $queue->clearQueue(true);

            Mage::getSingleton('adminhtml/session')->addSuccess('Indexing Queue has been cleared.');
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function resetAction()
    {
        try {
            $queueRunnerIndexer = Mage::getModel('index/indexer')
                ->getProcessByCode(Meilisearch_Search_Model_Indexer_Meilisearchqueuerunner::INDEXER_ID);
            $queueRunnerIndexer->setStatus(Mage_Index_Model_Process::STATUS_PENDING);
            $queueRunnerIndexer->save();

            Mage::getSingleton('adminhtml/session')->addSuccess('Indexing Queue has been reset.');
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    protected function _checkQueueIsActivated()
    {
        if (!Mage::helper('meilisearch_search/config')->isQueueActive()) {
            Mage::getSingleton('adminhtml/session')->addWarning(
                $this->__(
                    'The indexing queue is not enabled. Please activate it in your <a href="%s">Meilisearch configuration</a>.',
                    $this->getUrl('adminhtml/system_config/edit/section/meilisearch'),
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
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch_search/indexing_queue');
    }
}
