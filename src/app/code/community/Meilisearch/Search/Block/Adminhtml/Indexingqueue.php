<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Initialize Grid Container
     */
    public function __construct()
    {
        $this->_blockGroup = 'meilisearch_search';
        $this->_controller = 'adminhtml_indexingqueue';

        parent::__construct();

        $this->_removeButton('add');
        $this->_addButton('clear_queue', [
            'label' => Mage::helper('meilisearch_search')->__('Clear Queue'),
            'onclick' => "if (confirm('Are you sure you want to clear the queue? This operation cannot be reverted.')) { 
                location.href='" . $this->getUrl('*/*/clear') . "' };",
            'class' => 'cancel',
        ]);
    }

    /**
     * Get header text.
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search - Indexing Queue');
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
