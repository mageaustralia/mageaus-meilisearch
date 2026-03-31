<?php

/**
 * Source model for meilisearch remove words if no result.
 */
class Meilisearch_Search_Model_System_Removewords
{
    public function toOptionArray()
    {
        return [
            ['value' => 'none',          'label' => Mage::helper('meilisearch_search')->__('None')],
            ['value' => 'allOptional',   'label' => Mage::helper('meilisearch_search')->__('AllOptional')],
            ['value' => 'lastWords',     'label' => Mage::helper('meilisearch_search')->__('LastWords')],
            ['value' => 'firstWords',    'label' => Mage::helper('meilisearch_search')->__('FirstWords')],
        ];
    }
}
