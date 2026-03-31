<?php

/**
 * MeiliSearch Queue Job Model
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Model_Queue_Job extends Mage_Core_Model_Abstract
{
    /**
     * Initialize model
     */
    protected function _construct()
    {
        $this->_init('meilisearch_search/queue');
    }
}
