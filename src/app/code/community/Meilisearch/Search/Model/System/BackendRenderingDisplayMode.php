<?php

class Meilisearch_Search_Model_System_BackendRenderingDisplayMode
{
    public function toOptionArray()
    {
        return [
            ['value' => 'all',           'label' => Mage::helper('meilisearch_search')->__('All categories')],
            ['value' => 'only_products', 'label' => Mage::helper('meilisearch_search')->__('Categories without static blocks')],
        ];
    }
}
