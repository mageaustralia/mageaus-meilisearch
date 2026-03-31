<?php

class Meilisearch_Search_Block_Adminhtml_Reindexsku_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Meilisearch_MeilisearchSearch_Block_Adminhtml_ReindexSku_Edit_Form
     */
    #[\Override]
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/reindexPost'),
            'method' => 'post',
        ]);

        $fieldset = $form->addFieldset('base_fieldset', []);

        $html = '</br></br>';
        $html .= '<p>' . $this->__('Enter here the SKU(s) you want to reindex separated by commas or carriage returns.') . '</p>';
        $html .= '<p>' . $this->__('You will be notified if there is any reason why your product can\'t be reindexed.') . '</p>';
        $html .= '<p>' . $this->__('It can be :') . '</p>';
        $html .= '<ul style="list-style: disc; padding-left: 25px;">';
        $html .= '<li>' . $this->__('Product is disabled.') . '</li>';
        $html .= '<li>' . $this->__('Product is deleted.') . '</li>';
        $html .= '<li>' . $this->__('Product is out of stock.') . '</li>';
        $html .= '<li>' . $this->__('Product is not visible.') . '</li>';
        $html .= '<li>' . $this->__('Product is not related to the store.') . '</li>';
        $html .= '</ul>';
        $html .= '<p>' . $this->__('You can reindex up to 10 SKUs at once.') . '</p>';

        $fieldset->addField('skus', 'textarea', [
            'name' => 'skus',
            'label' => Mage::helper('meilisearch_search')->__('Product SKU(s)'),
            'title' => Mage::helper('meilisearch_search')->__('Product SKU(s)'),
            'required' => true,
            'style' => 'width:100%',
            'after_element_html' => $html,
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
