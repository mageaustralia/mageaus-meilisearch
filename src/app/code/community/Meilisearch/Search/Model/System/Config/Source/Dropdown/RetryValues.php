<?php

class Meilisearch_Search_Model_System_Config_Source_Dropdown_RetryValues
{
    public function toOptionArray()
    {
        return [
            ['value' => '1','label' => '1'],
            ['value' => '2','label' => '2'],
            ['value' => '3','label' => '3'],
            ['value' => '5','label' => '5'],
            ['value' => '10','label' => '10'],
            ['value' => '20','label' => '20'],
            ['value' => '50','label' => '50'],
            ['value' => '100','label' => '100'],
            ['value' => '9999999','label' => 'unlimited'],
        ];
    }
}
