<?php

/**
 * Meilisearch custom sort order field.
 */
class Meilisearch_Search_Block_System_Config_Form_Field_Sorts extends Meilisearch_Search_Block_System_Config_Form_Field_AbstractField
{
    public function __construct()
    {
        $this->settings = [
            'columns' => [
                'attribute' => [
                    'label'   => 'Attribute',
                    'options' => function () {
                        $options = [];

                        /** @var Meilisearch_Search_Helper_Entity_Producthelper $product_helper */
                        $product_helper = Mage::helper('meilisearch_search/entity_producthelper');
                        $attributes = $product_helper->getAllAttributes();
                        foreach ($attributes as $key => $label) {
                            $options[$key] = $key ?: $label;
                        }

                        return $options;
                    },
                    'rowMethod' => 'getAttribute',
                    'width'     => 160,
                ],
                'sort' => [
                    'label'   => 'Sort',
                    'options' => [
                        'asc'  => 'Ascending',
                        'desc' => 'Descending',
                    ],
                    'rowMethod' => 'getSort',
                ],
                'label' => [
                    'label' => 'Label',
                    'style' => 'width: 200px;',
                ],
            ],
            'buttonLabel' => 'Add Sorting Attribute',
            'addAfter'    => false,
        ];

        parent::__construct();
    }
}
