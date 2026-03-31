<?php

class Meilisearch_Search_Block_System_Config_Form_Field_Select extends Mage_Adminhtml_Block_Html_Select
{
    #[\Override]
    protected function _toHtml()
    {
        $this->setName($this->getInputName());
        $this->setClass('select');

        // Generate a unique ID if one isn't already set
        if (!$this->getId()) {
            $this->setId($this->getInputName() . '_' . uniqid());
        }

        return trim((string) preg_replace('/\s+/', ' ', parent::_toHtml()));
    }
}
