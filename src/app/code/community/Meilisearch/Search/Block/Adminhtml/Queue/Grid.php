<?php

/**
 * MeiliSearch Queue Grid
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Block_Adminhtml_Queue_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('meilisearchQueueGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * Prepare collection
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('meilisearch_search/queue_job')->getCollection();

        // Add store information
        $collection->getSelect()->joinLeft(
            ['store' => $collection->getTable('core/store')],
            'main_table.store_id = store.store_id',
            ['store_name' => 'name'],
        );

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare grid columns
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('job_id', [
            'header'    => Mage::helper('meilisearch_search')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'type'      => 'number',
            'index'     => 'job_id',
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('meilisearch_search')->__('Created'),
            'align'     => 'left',
            'width'     => '150px',
            'type'      => 'datetime',
            'index'     => 'created_at',
        ]);

        $this->addColumn('class', [
            'header'    => Mage::helper('meilisearch_search')->__('Type'),
            'align'     => 'left',
            'width'     => '150px',
            'index'     => 'class',
            'type'      => 'options',
            'options'   => [
                'Meilisearch_Search_Helper_Entity_Producthelper' => Mage::helper('meilisearch_search')->__('Product'),
                'Meilisearch_Search_Helper_Entity_Categoryhelper' => Mage::helper('meilisearch_search')->__('Category'),
                'Meilisearch_Search_Helper_Entity_Pagehelper' => Mage::helper('meilisearch_search')->__('CMS Page'),
                'Meilisearch_Search_Helper_Entity_Suggestionhelper' => Mage::helper('meilisearch_search')->__('Suggestion'),
                'Meilisearch_Search_Helper_Entity_Additionalsectionshelper' => Mage::helper('meilisearch_search')->__('Additional Section'),
            ],
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_type',
        ]);

        $this->addColumn('method', [
            'header'    => Mage::helper('meilisearch_search')->__('Action'),
            'align'     => 'left',
            'width'     => '100px',
            'index'     => 'method',
            'type'      => 'options',
            'options'   => [
                'addObjects' => Mage::helper('meilisearch_search')->__('Add/Update'),
                'deleteObjects' => Mage::helper('meilisearch_search')->__('Delete'),
                'moveIndex' => Mage::helper('meilisearch_search')->__('Move Index'),
                'deleteIndex' => Mage::helper('meilisearch_search')->__('Delete Index'),
            ],
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_action',
        ]);

        $this->addColumn('store_name', [
            'header'    => Mage::helper('meilisearch_search')->__('Store'),
            'align'     => 'left',
            'width'     => '150px',
            'index'     => 'store_name',
            'filter_index' => 'store.name',
        ]);

        $this->addColumn('data_size', [
            'header'    => Mage::helper('meilisearch_search')->__('Data Size'),
            'align'     => 'left',
            'width'     => '100px',
            'index'     => 'data_size',
            'type'      => 'number',
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_size',
        ]);

        $this->addColumn('pid', [
            'header'    => Mage::helper('meilisearch_search')->__('Object IDs'),
            'align'     => 'left',
            'index'     => 'pid',
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_pids',
        ]);

        $this->addColumn('retries', [
            'header'    => Mage::helper('meilisearch_search')->__('Retries'),
            'align'     => 'center',
            'width'     => '80px',
            'type'      => 'number',
            'index'     => 'retries',
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_retries',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('meilisearch_search')->__('Status'),
            'align'     => 'center',
            'width'     => '100px',
            'index'     => 'retries',
            'type'      => 'options',
            'options'   => [
                '0' => Mage::helper('meilisearch_search')->__('Pending'),
                '1' => Mage::helper('meilisearch_search')->__('Processing'),
                '2' => Mage::helper('meilisearch_search')->__('Retrying'),
                '3' => Mage::helper('meilisearch_search')->__('Failed'),
            ],
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_status',
        ]);

        $this->addColumn('error_log', [
            'header'    => Mage::helper('meilisearch_search')->__('Error'),
            'align'     => 'left',
            'index'     => 'error_log',
            'renderer'  => 'meilisearch_search/adminhtml_queue_grid_renderer_error',
        ]);

        $this->addExportType('*/*/exportCsv', Mage::helper('meilisearch_search')->__('CSV'));

        return parent::_prepareColumns();
    }

    /**
     * Prepare mass actions
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('job_id');
        $this->getMassactionBlock()->setFormFieldName('queue');

        $this->getMassactionBlock()->addItem('delete', [
            'label'    => Mage::helper('meilisearch_search')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('meilisearch_search')->__('Are you sure you want to delete selected items?'),
        ]);

        $this->getMassactionBlock()->addItem('retry', [
            'label'    => Mage::helper('meilisearch_search')->__('Reset for Retry'),
            'url'      => $this->getUrl('*/*/massRetry'),
            'confirm'  => Mage::helper('meilisearch_search')->__('Are you sure you want to reset selected items for retry?'),
        ]);

        return $this;
    }

    /**
     * Get grid URL
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    /**
     * Get row URL - no row editing for queue items
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return false;
    }
}
