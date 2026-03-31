<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Meilisearch_Search_Block_Adminhtml_IndexingQueue_Edit_Form
     */
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('meilisearch_current_job');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/updatePost'),
            'method' => 'post',
        ]);

        $fieldset = $form->addFieldset('base_fieldset', []);
        $readOnlyStyle = 'border: 0; background: none;';

        $fieldset->addField('job_id', 'text', [
            'name' => 'job_id',
            'label' => Mage::helper('meilisearch_search')->__('Job ID'),
            'title' => Mage::helper('meilisearch_search')->__('Job ID'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('created', 'text', [
            'name' => 'created',
            'label' => Mage::helper('meilisearch_search')->__('Created'),
            'title' => Mage::helper('meilisearch_search')->__('Created'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('status', 'text', [
            'name' => 'status',
            'label' => Mage::helper('meilisearch_search')->__('Status'),
            'title' => Mage::helper('meilisearch_search')->__('Status'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('pid', 'text', [
            'name' => 'pid',
            'label' => Mage::helper('meilisearch_search')->__('PID'),
            'title' => Mage::helper('meilisearch_search')->__('PID'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('class', 'text', [
            'name' => 'class',
            'label' => Mage::helper('meilisearch_search')->__('Class'),
            'title' => Mage::helper('meilisearch_search')->__('Class'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('method', 'text', [
            'name' => 'method',
            'label' => Mage::helper('meilisearch_search')->__('Method'),
            'title' => Mage::helper('meilisearch_search')->__('Method'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('data', 'textarea', [
            'name' => 'data',
            'label' => Mage::helper('meilisearch_search')->__('Data'),
            'title' => Mage::helper('meilisearch_search')->__('Data'),
            'readonly' => true,
        ]);

        $fieldset->addField('max_retries', 'text', [
            'name' => 'max_retries',
            'label' => Mage::helper('meilisearch_search')->__('Max Retries'),
            'title' => Mage::helper('meilisearch_search')->__('Max Retries'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('retries', 'text', [
            'name' => 'retries',
            'label' => Mage::helper('meilisearch_search')->__('Retries'),
            'title' => Mage::helper('meilisearch_search')->__('Retries'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('data_size', 'text', [
            'name' => 'data_size',
            'label' => Mage::helper('meilisearch_search')->__('Data Size'),
            'title' => Mage::helper('meilisearch_search')->__('Data Size'),
            'readonly' => true,
            'style' => $readOnlyStyle,
        ]);

        $fieldset->addField('error_log', 'textarea', [
            'name' => 'error_log',
            'label' => Mage::helper('meilisearch_search')->__('Error Log'),
            'title' => Mage::helper('meilisearch_search')->__('Error Log'),
            'readonly' => true,
        ]);


        $form->setValues($model->getData());
        $form->addValues([
            'status' => $model->getStatusLabel(),
        ]);
        $form->setUseContainer(true);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
