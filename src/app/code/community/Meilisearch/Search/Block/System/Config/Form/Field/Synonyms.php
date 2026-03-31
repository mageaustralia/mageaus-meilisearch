<?php

class Meilisearch_Search_Block_System_Config_Form_Field_Synonyms extends Meilisearch_Search_Block_System_Config_Form_Field_AbstractField
{
    public function __construct()
    {
        $this->settings = [
            'columns' => [
                'synonyms' => [
                    'label' => 'Synonyms (comma-separated)',
                    'style' => 'width: 550px;',
                ],
            ],
            'buttonLabel' => 'Add Synonyms',
            'addAfter'    => false,
        ];

        parent::__construct();
    }
}
