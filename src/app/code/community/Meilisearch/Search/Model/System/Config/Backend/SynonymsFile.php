<?php

class Meilisearch_Search_Model_System_Config_Backend_SynonymsFile extends Mage_Adminhtml_Model_System_Config_Backend_File
{
    #[\Override]
    protected function _getAllowedExtensions()
    {
        return ['json'];
    }
}
