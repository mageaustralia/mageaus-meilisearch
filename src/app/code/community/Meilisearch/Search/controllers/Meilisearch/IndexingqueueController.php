<?php

/**
 * MeiliSearch Indexing Queue Controller
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Meilisearch_IndexingqueueController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Initialize action
     *
     * @return Meilisearch_Search_Meilisearch_IndexingqueueController
     */
    protected function _initAction()
    {
        $this->_checkQueueIsActivated();

        $this->loadLayout()
            ->_setActiveMenu('system/meilisearch/indexing_queue')
            ->_addBreadcrumb(
                Mage::helper('meilisearch_search')->__('System'),
                Mage::helper('meilisearch_search')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('meilisearch_search')->__('MeiliSearch Search'),
                Mage::helper('meilisearch_search')->__('MeiliSearch Search'),
            )
            ->_addBreadcrumb(
                Mage::helper('meilisearch_search')->__('Indexing Queue'),
                Mage::helper('meilisearch_search')->__('Indexing Queue'),
            );
        return $this;
    }

    /**
     * Controller predispatch method
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    #[\Override]
    public function preDispatch()
    {
        return parent::preDispatch();
    }

    /**
     * Index action - display the queue grid
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
             ->_title($this->__('MeiliSearch Search'))
             ->_title($this->__('Indexing Queue'));

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * View action - view a specific queue item
     */
    public function viewAction()
    {
        $this->_title($this->__('System'))
             ->_title($this->__('MeiliSearch Search'))
             ->_title($this->__('Queue Item Details'));

        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('meilisearch_search/queue')->load($id);

        if ($model->getId()) {
            Mage::register('current_queue_item', $model);
            $this->_initAction();
            $this->renderLayout();
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('meilisearch_search')->__('Queue item does not exist'),
            );
            $this->_redirect('*/*/');
        }
    }

    /**
     * Check if queue is activated
     *
     * @return void
     */
    protected function _checkQueueIsActivated()
    {
        $config = Mage::helper('meilisearch_search/config');

        if (!$config->isQueueActive()) {
            $message = 'Queue processing is disabled. To activate it, please go to <a href="'
                     . $this->getUrl('adminhtml/system_config/edit/section/meilisearch/')
                     . '">MeiliSearch configuration</a> and enable queue.';
            Mage::getSingleton('adminhtml/session')->addNotice($message);
        }
    }

    /**
     * Check admin permissions
     *
     * @return bool
     */
    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch/indexing_queue');
    }
}
