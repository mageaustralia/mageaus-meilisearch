<?php

/**
 * MeiliSearch Queue Resource Model
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Model_Resource_Queue extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('meilisearch_search/queue', 'job_id');
    }
}
