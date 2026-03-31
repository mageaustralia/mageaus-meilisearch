<?php

class Meilisearch_Search_Helper_Entity_Suggestionhelper extends Meilisearch_Search_Helper_Entity_Helper
{
    protected $_popularQueries = null;
    protected $_popularQueriesCacheId = 'meilisearch_popular_queries_cache_tag';

    protected function getIndexNameSuffix()
    {
        return '_suggestions';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes'   => ['unordered(query)'],
            'customRanking'          => ['desc(popularity)', 'desc(number_of_results)', 'asc(date)'],
            'typoTolerance'          => false,
            'attributesToRetrieve'   => ['query'],
            'removeWordsIfNoResults' => 'lastWords',
        ];

        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_suggestions_index_before_set_settings', ['store_id' => $storeId, 'index_settings' => $transport]);
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getObject(Mage_CatalogSearch_Model_Query $suggestion)
    {
        $suggestionObject = [
            'objectID'          => $suggestion->getData('query_id'),
            'query'             => $suggestion->getData('query_text'),
            'number_of_results' => (int) $suggestion->getData('num_results'),
            'popularity'        => (int) $suggestion->getData('popularity'),
            'updated_at'        => (int) strtotime((string) $suggestion->getData('updated_at')),
        ];

        $transport = new Varien_Object($suggestionObject);
        Mage::dispatchEvent('meilisearch_after_create_suggestion_object', ['suggestion' => $transport, 'suggestionObject' => $suggestion]);
        $suggestionObject = $transport->getData();

        return $suggestionObject;
    }

    public function getPopularQueries($storeId)
    {
        if ($this->_popularQueries == null) {

            // load from cache if we can
            $cachedPopularQueries = Mage::app()->loadCache($this->_popularQueriesCacheId);
            if ($cachedPopularQueries) {
                $this->_popularQueries = unserialize((string) $cachedPopularQueries);
            } else {
                $collection = Mage::getResourceModel('catalogsearch/query_collection');
                $collection->getSelect()->where('num_results >= ' . $this->config->getMinNumberOfResults() . ' AND popularity >= ' . $this->config->getMinPopularity() . ' AND query_text != "__empty__"');
                $collection->getSelect()->limit(12);
                $collection->setOrder('popularity', 'DESC');
                $collection->setOrder('num_results', 'DESC');
                $collection->setOrder('updated_at', 'ASC');

                if ($storeId) {
                    $collection->getSelect()->where('store_id = ?', (int) $storeId);
                }

                $collection->load();

                $suggestions = [];

                /** @var $suggestion Mage_Catalog_Model_Category */
                foreach ($collection as $suggestion) {
                    if (strlen((string) $suggestion['query_text']) >= 3) {
                        $suggestions[] = $suggestion['query_text'];
                    }
                }

                $this->_popularQueries = array_slice($suggestions, 0, 9);
                try { //save to cache
                    $cacheContent = serialize($this->_popularQueries);
                    $tags = [
                        Mage_CatalogSearch_Model_Query::CACHE_TAG,
                    ];

                    Mage::app()->saveCache($cacheContent, $this->_popularQueriesCacheId, $tags, 604800);
                } catch (Exception $e) {
                    // Exception = no caching
                    Mage::logException($e);
                }
            }
        }

        return $this->_popularQueries;
    }

    public function getSuggestionCollectionQuery($storeId)
    {
        /** @var Mage_CatalogSearch_Model_Resource_Query_Collection $collection */
        $collection = Mage::getResourceModel('catalogsearch/query_collection')
                            ->addStoreFilter($storeId)
                            ->setStoreId($storeId);

        $collection->getSelect()->where('num_results >= ' . $this->config->getMinNumberOfResults() . ' AND popularity >= ' . $this->config->getMinPopularity() . ' AND query_text != "__empty__"');

        Mage::dispatchEvent('meilisearch_after_suggestions_collection_build', ['store' => $storeId, 'collection' => $collection]);

        return $collection;
    }
}
