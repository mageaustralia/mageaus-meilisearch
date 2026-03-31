<?php

class Meilisearch_Search_Model_System_Config_Backend_Serialized_Array extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{
    #[\Override]
    protected function _afterLoad()
    {
        /** @var Meilisearch_Search_Helper_Data $helper */
        $helper = Mage::helper('meilisearch_search');
        if ($helper->isX3Version()) {
            if (!is_array($this->getValue())) {
                $value = $this->getValue();
                $this->setValue(empty($value) ? false : unserialize((string) $value));
            }

            return;
        }

        parent::_afterLoad();
    }
}
