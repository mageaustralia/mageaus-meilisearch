<?php

/**
 * MeiliSearch Queue Collection
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Model_Resource_Queue_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize collection
     */
    protected function _construct()
    {
        $this->_init('meilisearch_search/queue_job');
    }
}
