<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Internal constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'job_id';
        $this->_blockGroup = 'meilisearch';
        $this->_controller = 'adminhtml_indexingqueue';

        $this->_removeButton('save');
        $this->_removeButton('reset');
        $this->_removeButton('delete');
    }

    /**
     * Get header text.
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('meilisearch_search')->__(
            'Meilisearch Search - Indexing Queue Job #%s',
            Mage::registry('meilisearch_current_job')->getJobId(),
        );
    }

    /**
     * Set custom Meilisearch icon class.
     *
     * @return string
     */
    #[\Override]
    public function getHeaderCssClass()
    {
        return 'icon-head meilisearch-head-icon';
    }
}
