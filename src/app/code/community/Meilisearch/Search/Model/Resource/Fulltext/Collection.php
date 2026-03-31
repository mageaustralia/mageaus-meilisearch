<?php

class Meilisearch_Search_Model_Resource_Fulltext_Collection extends Mage_CatalogSearch_Model_Resource_Fulltext_Collection
{
    /**
     * Get found products ids
     *
     * @return array|Meilisearch_Search_Model_Resource_Fulltext_Collection
     */
    #[\Override]
    public function getFoundIds()
    {
        if (!$this->_helper()->isX3Version()) {
            // For older versions, search is handled in addSearchFilter
            // We need to check if we already have Meilisearch data
            $query = $this->_getQuery();
            if (!empty($query)) {
                $data = $this->getMeilisearchData($query);
                if ($data !== false) {
                    return array_keys($data);
                }
            }
            return parent::getFoundIds();
        }

        $query = $this->_getQuery();
        if (is_null($this->_foundData) && !empty($query) && $query instanceof Mage_CatalogSearch_Model_Query) {
            $data = $this->getMeilisearchData($query->getQueryText());
            if (false === $data) {
                return parent::getFoundIds();
            }

            $this->_foundData = $data;
        }

        return parent::getFoundIds();
    }

    /**
     * @return Meilisearch_Search_Helper_Data
     */
    protected function _helper()
    {
        return Mage::helper('meilisearch_search');
    }

    /**
     * @param string $query
     *
     * @return array|bool
     */
    protected function getMeilisearchData($query)
    {
        /** @var Meilisearch_Search_Helper_Config $config */
        $config  = Mage::helper('meilisearch_search/config');
        $storeId = Mage::app()->getStore()->getId();

        if (!$config->getServerUrl() || !$config->getAPIKey() || $config->isEnabledFrontEnd($storeId) === false) {
            return false;
        }

        $data = [];

        if ($config->isInstantEnabled($storeId) === false || $config->makeSeoRequest($storeId)) {
            $meilisearch_query = $query !== '__empty__' ? $query : '';

            try {
                $data = $this->_helper()->getSearchResult($meilisearch_query, $storeId);
            } catch (\Exception $e) {
                /** @var Meilisearch_Search_Helper_Logger $logger */
                $logger = Mage::helper('meilisearch_search/logger');

                $logger->log($e->getMessage(), true);
                $logger->log($e->getTraceAsString(), true);

                return false;
            }
        }

        return $data;
    }

    /**
     * Get query text
     *
     * @return string|null
     */
    #[\Override]
    protected function _getQuery()
    {
        $query = Mage::helper('catalogsearch')->getQuery();
        if ($query) {
            return $query->getQueryText();
        }
        return null;
    }

    /**
     * @param string $query
     *
     * @return Meilisearch_Search_Model_Resource_Fulltext_Collection
     */
    #[\Override]
    public function addSearchFilter($query)
    {
        if ($this->_helper()->isX3Version()) {
            return $this;
        }

        $data = $this->getMeilisearchData($query);
        if (false === $data) {
            return parent::addSearchFilter($query);
        }
        $sortedIds = array_reverse(array_keys($data));

        if (!empty($sortedIds)) {
            $this->getSelect()->columns([
                'relevance' => new Maho\Db\Expr("FIND_IN_SET(e.entity_id, '" . implode(',', $sortedIds) . "')"),
            ]);
            $this->getSelect()->where('e.entity_id IN (?)', $sortedIds);
        } else {
            // No results, return empty collection
            $this->getSelect()->where('1=0');
        }

        return $this;
    }
}
