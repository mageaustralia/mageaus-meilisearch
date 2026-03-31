<?php

class Meilisearch_Search_Block_Adminhtml_Reindexsku_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Internal constructor.
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $this->_objectId = 'sku';
        $this->_blockGroup = 'meilisearch_search';
        $this->_controller = 'adminhtml_reindexsku';
    }

    /**
     * Get header text.
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('meilisearch_search')->__('Meilisearch Search - Reindex SKU(s)');
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
