<?php

/**
 * Source model for select image type.
 */
class Meilisearch_Search_Model_System_Imagetype
{
    public function toOptionArray()
    {
        return [
            ['value' => 'image',         'label' => Mage::helper('core')->__('Base Image')],
            ['value' => 'small_image',   'label' => Mage::helper('core')->__('Small Image')],
            ['value' => 'thumbnail',     'label' => Mage::helper('core')->__('Thumbnail')],
        ];
    }
}
