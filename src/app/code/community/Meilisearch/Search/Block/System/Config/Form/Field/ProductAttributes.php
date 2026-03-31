<?php

/**
 * Meilisearch custom sort order field.
 */
class Meilisearch_Search_Block_System_Config_Form_Field_ProductAttributes extends Meilisearch_Search_Block_System_Config_Form_Field_AbstractField
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

                        $options['custom_attribute'] = '[use custom attribute]';

                        return $options;
                    },
                    'rowMethod' => 'getAttribute',
                    'width' => 150,
                ],
            ],
            'buttonLabel' => 'Add Attribute',
            'addAfter'    => false,
        ];

        parent::__construct();
    }
}
