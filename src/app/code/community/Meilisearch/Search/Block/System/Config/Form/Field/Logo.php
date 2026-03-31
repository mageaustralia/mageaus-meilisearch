<?php

class Meilisearch_Search_Block_System_Config_Form_Field_Logo extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected $_showUpsell = false;

    #[\Override]
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->showLogo()) {
            $element->setDisabled(true);
            $element->setValue(0);
            $this->_showUpsell = true;
        }

        return parent::_getElementHtml($element);
    }

    /**
     * @return bool
     */
    public function showLogo()
    {
        $proxyHelper = Mage::helper('meilisearch_search/proxyHelper');
        $info = $proxyHelper->getClientConfigurationData();

        return isset($info['require_logo']) && $info['require_logo'] == 1;
    }

    #[\Override]
    protected function _decorateRowHtml($element, $html)
    {
        if (!$this->_showUpsell) {
            return parent::_decorateRowHtml($element, $html);
        }

        $additionalRow = '<tr class="meilisearch-messages"><td></td><td colspan="3"><div class="meilisearch-config-info icon-stars">';
        $additionalRow .= $this->__(
            'To be able to remove the Meilisearch logo, please consider <a href="%s" target="_blank">upgrading to a higher plan.</a>',
            'https://www.meilisearch.com/pricing/',
        );
        $additionalRow .= '</div></td></tr>';

        return '<tr id="row_' . $element->getHtmlId() . '">' . $html . '</tr>' . $additionalRow;
    }
}
