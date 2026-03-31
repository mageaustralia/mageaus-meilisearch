<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Initialize Grid Properties
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('job_id');
        $this->setDefaultSort('job_id');
        $this->setDefaultDir('acs');
    }

    /**
     * Prepare Search Report collection for grid
     *
     * @return Mage_Adminhtml_Block_Report_Search_Grid
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('meilisearch_search/job_collection');
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare Grid columns
     *
     * @return Mage_Adminhtml_Block_Report_Search_Grid
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('job_id', [
            'header' => Mage::helper('meilisearch_search')->__('Job ID'),
            'width' => '50px',
            'filter' => false,
            'index' => 'job_id',
            'type' => 'number',
        ]);

        $this->addColumn('created', [
            'header' => Mage::helper('meilisearch_search')->__('Created'),
            'index' => 'created',
            'type' => 'datetime',
        ]);

        $this->addColumn('status', [
            'header' => Mage::helper('meilisearch_search')->__('Status'),
            'index' => 'status',
            'getter' => 'getStatusLabel',
            'filter' => false,
        ]);

        $this->addColumn('method', [
            'header' => Mage::helper('meilisearch_search')->__('Method'),
            'index' => 'method',
            'type' => 'options',
            'options' => Mage::getModel('meilisearch_search/source_jobMethods')->getMethods(),
        ]);

        $this->addColumn('data', [
            'header' => Mage::helper('meilisearch_search')->__('Data'),
            'index' => 'data',
            'renderer' => 'Meilisearch_Search_Block_Adminhtml_Indexingqueue_Grid_Renderer_Json',
        ]);

        $this->addColumn('max_retries', [
            'header' => Mage::helper('meilisearch_search')->__('Max Retries'),
            'width' => '40px',
            'filter' => false,
            'index' => 'max_retries',
            'type' => 'number',
        ]);

        $this->addColumn('retries', [
            'header' => Mage::helper('meilisearch_search')->__('Retries'),
            'width' => '40px',
            'filter' => false,
            'index' => 'retries',
            'type' => 'number',
        ]);

        $this->addColumn(
            'action',
            [
                'header'    => Mage::helper('meilisearch_search')->__('Action'),
                'width'     => '50px',
                'type'      => 'action',
                'getter'     => 'getJobId',
                'actions'   => [
                    [
                        'caption' => Mage::helper('meilisearch_search')->__('View'),
                        'url'     => ['base' => '*/*/view'],
                        'field'   => 'id',
                    ],
                ],
                'filter'    => false,
                'sortable'  => false,
            ],
        );

        return parent::_prepareColumns();
    }

    /**
     * Retrieve Row Click callback URL
     *
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', ['id' => $row->getJobId()]);
    }
}
