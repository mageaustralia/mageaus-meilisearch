<?php

// Include Meilisearch autoloader for OpenMage
require_once dirname(__FILE__, 2) . '/Model/Autoloader.php';

class Meilisearch_Search_Helper_Meilisearchhelper extends Mage_Core_Helper_Abstract
{
    /** @var \Meilisearch\Client */
    protected $client;

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var int */
    protected $maxRecordSize;

    /** @var array */
    protected $potentiallyLongAttributes = ['description', 'short_description', 'meta_description', 'content'];

    /** @var string */
    private $lastUsedIndexName;

    /** @var int */
    private $lastTaskId;

    /** @var mixed */
    private $lastTask;

    public function __construct()
    {
        $this->config = Mage::helper('meilisearch_search/config');
        $this->resetCredentialsFromConfig();
    }

    /**
     * Extract task UID from response (handles both Task objects and arrays)
     *
     * @param mixed $response
     * @return string|null
     */
    protected function extractTaskUid($response)
    {
        if (is_object($response) && method_exists($response, 'getTaskUid')) {
            return $response->getTaskUid();
        } elseif (is_array($response) && isset($response['taskUid'])) {
            return $response['taskUid'];
        }
        return null;
    }

    /**
     * Wait for a task to complete. Accepts either a Task object (new SDK),
     * an array response ({"taskUid": N, ...}), or a scalar UID.
     *
     * Previously this no-op'd on scalar UIDs, which meant moveIndex()'s
     * atomicity guarantee silently broke whenever the SDK returned a scalar —
     * deleteIndex(tmp) could run before the swap completed and nuke the live
     * index. Now we always resolve to a task and poll to completion.
     *
     * @param mixed $taskOrUid Task object, array, or task UID
     * @return array|null Final task payload (for status checks), or null on
     *                    unresolvable input
     */
    protected function waitForTask($taskOrUid)
    {
        if (is_object($taskOrUid) && method_exists($taskOrUid, 'wait')) {
            $taskOrUid->wait();
            return method_exists($taskOrUid, 'toArray') ? $taskOrUid->toArray() : null;
        }

        $uid = $this->extractTaskUid($taskOrUid);
        if ($uid === null && (is_numeric($taskOrUid) || is_string($taskOrUid))) {
            $uid = $taskOrUid;
        }
        if ($uid === null || $this->client === null) {
            return null;
        }

        // Client-level waitForTask is available on all supported SDK versions.
        try {
            $task = $this->client->waitForTask($uid);
            if (is_object($task) && method_exists($task, 'toArray')) {
                return $task->toArray();
            }
            return is_array($task) ? $task : null;
        } catch (\Exception $e) {
            Mage::log('Meilisearch waitForTask failed for uid ' . $uid . ': ' . $e->getMessage(), null, 'meilisearch_error.log');
            return null;
        }
    }

    /**
     * Verify the last task succeeded. Throws on a failed task so callers
     * (indexer, queue runner) can abort before promoting a partially-built
     * tmp index or returning a success status that isn't true.
     *
     * @throws Meilisearch_Search_Model_Exception_IndexPendingException
     */
    protected function assertTaskSucceeded($taskResult, $context = '')
    {
        if (!is_array($taskResult)) {
            return; // Couldn't resolve, don't block.
        }
        $status = $taskResult['status'] ?? null;
        if ($status === 'failed' || $status === 'canceled') {
            $error = $taskResult['error']['message'] ?? 'unknown error';
            throw new Exception(sprintf(
                'Meilisearch task %s %s: %s',
                $taskResult['uid'] ?? '?',
                $status,
                $context ? "($context) $error" : $error,
            ));
        }
    }

    public function resetCredentialsFromConfig()
    {
        $serverUrl = trim((string) $this->config->getServerUrl());
        $apiKey = trim((string) $this->config->getAPIKey());

        if ($serverUrl && $apiKey) {
            try {
                $this->client = new \Meilisearch\Client($serverUrl, $apiKey);
                // Don't test connection immediately - it may fail during indexer enumeration
            } catch (\Exception $e) {
                Mage::log('Meilisearch client creation error: ' . $e->getMessage(), null, 'meilisearch_error.log');
                $this->client = null;
            }
        }
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getIndex($name)
    {
        // Create index if it doesn't exist
        try {
            $this->client->getIndex($name);
        } catch (\Exception) {
            $this->client->createIndex($name, ['primaryKey' => 'objectID']);
        }

        return $this->client->index($name);
    }

    public function listIndexes()
    {
        $indexes = $this->client->getIndexes();
        $result = ['items' => []];

        foreach ($indexes as $index) {
            $result['items'][] = [
                'name' => $index->getUid(),
                'entries' => $index->getNumberOfDocuments(),
                'dataSize' => 0, // Meilisearch doesn't provide this directly
                'fileSize' => 0,
                'lastBuildTimeS' => 0,
                'numberOfPendingTasks' => 0,
                'pendingTask' => false,
            ];
        }

        return $result;
    }

    public function query($indexName, $q, $params)
    {
        $index = $this->client->index($indexName);

        // Convert Algolia params to Meilisearch params
        $meilisearchParams = $this->convertSearchParams($params);

        // Store the index name for use in convertSearchParams
        $this->lastUsedIndexName = $indexName;

        $searchResult = $index->search($q, $meilisearchParams);

        // Convert Meilisearch SearchResult object to Algolia-compatible array format
        return [
            'hits' => $searchResult->getHits(),
            'nbHits' => $searchResult->getEstimatedTotalHits(),
            'page' => $searchResult->getPage() ?? 0,
            'nbPages' => ceil($searchResult->getEstimatedTotalHits() / ($meilisearchParams['limit'] ?? 20)),
            'hitsPerPage' => $meilisearchParams['limit'] ?? 20,
            'processingTimeMS' => $searchResult->getProcessingTimeMs(),
            'query' => $searchResult->getQuery(),
            'params' => http_build_query($params),
        ];
    }

    public function getObjects($indexName, $objectIds)
    {
        $index = $this->client->index($indexName);

        // Meilisearch getDocuments() expects a DocumentsQuery object or null
        $results = [];
        foreach ($objectIds as $objectId) {
            try {
                $doc = $index->getDocument($objectId);
                $results[] = $doc;
            } catch (\Exception) {
                // Document not found, skip
            }
        }

        return ['results' => $results];
    }

    public function setSettings($indexName, $settings, $forwardToReplicas = false)
    {
        // Create index if it doesn't exist
        try {
            $this->client->getIndex($indexName);
        } catch (\Exception) {
            $this->client->createIndex($indexName, ['primaryKey' => 'objectID']);
        }

        $index = $this->client->index($indexName);

        // Convert Algolia settings to Meilisearch settings
        $meilisearchSettings = $this->convertIndexSettings($settings);

        // Debug logging
        Mage::log('Meilisearch settings for ' . $indexName . ': ' . json_encode($meilisearchSettings), null, 'meilisearch_debug.log');

        // Additional check for empty arrays that should be objects
        foreach ($meilisearchSettings as $key => &$value) {
            if (is_array($value) && empty($value) && in_array($key, ['synonyms', 'stopWords'])) {
                $value = new \stdClass();
            }
        }

        // If settings is empty array, don't update
        if (empty($meilisearchSettings)) {
            return ['taskID' => 0];
        }

        $res = $index->updateSettings($meilisearchSettings);

        $this->lastUsedIndexName = $indexName;
        $this->lastTask = $res;
        $this->lastTaskId = $this->extractTaskUid($res);

        return ['taskID' => $this->extractTaskUid($res)];
    }

    public function clearIndex($indexName)
    {
        $index = $this->client->index($indexName);
        $res = $index->deleteAllDocuments();

        $this->lastUsedIndexName = $indexName;
        $this->lastTask = $res;
        $this->lastTaskId = $this->extractTaskUid($res);

        return ['taskID' => $this->extractTaskUid($res)];
    }

    public function deleteIndex($indexName)
    {
        $res = $this->client->deleteIndex($indexName);

        $this->lastUsedIndexName = $indexName;
        $this->lastTask = $res;
        $this->lastTaskId = $this->extractTaskUid($res);

        return ['taskID' => $this->extractTaskUid($res)];
    }

    public function deleteObjects($ids, $indexName)
    {
        $index = $this->client->index($indexName);
        $res = $index->deleteDocuments($ids);

        $this->lastUsedIndexName = $indexName;
        $this->lastTask = $res;
        $this->lastTaskId = $this->extractTaskUid($res);

        return ['taskID' => $this->extractTaskUid($res)];
    }

    public function deleteObject($indexName, $objectId)
    {
        return $this->deleteObjects([$objectId], $indexName);
    }

    public function moveIndex($tmpIndexName, $indexName)
    {
        // Use Meilisearch's native swap-indexes API (v0.30+).
        // Atomicity depends on waiting for the swap task to succeed BEFORE
        // deleting the (now-aliased-tmp) old index. Any prior silent no-op
        // in waitForTask() would race deleteIndex past the live swap.
        try {
            try {
                $this->client->getIndex($indexName);
            } catch (\Exception) {
                $createRes = $this->client->createIndex($indexName, ['primaryKey' => 'objectID']);
                $this->assertTaskSucceeded($this->waitForTask($createRes), "createIndex($indexName)");
            }

            $swapRes = $this->client->swapIndexes([
                [$tmpIndexName, $indexName],
            ]);
            $this->assertTaskSucceeded($this->waitForTask($swapRes), "swapIndexes($tmpIndexName,$indexName)");

            $deleteRes = $this->client->deleteIndex($tmpIndexName);
            return ['taskID' => $this->extractTaskUid($deleteRes)];
        } catch (\Exception $e) {
            Mage::log('Meilisearch moveIndex error: ' . $e->getMessage(), 3, 'meilisearch.log');
            // Fallback: try to clean up the tmp index so the next run has room.
            try {
                $this->client->deleteIndex($tmpIndexName);
            } catch (\Exception) {
            }
            return ['taskID' => 0];
        }
    }

    public function mergeSettings($indexName, $settings)
    {
        $onlineSettings = [];

        try {
            $index = $this->client->index($indexName);
            $onlineSettings = $index->getSettings();
        } catch (\Exception) {
            // Index might not exist yet
        }

        $settings = $this->castSettings($settings);

        foreach ($settings as $key => $value) {
            $onlineSettings[$key] = $value;
        }

        return $onlineSettings;
    }

    public function addObjects($objects, $indexName)
    {
        // Create index if it doesn't exist
        try {
            $this->client->getIndex($indexName);
        } catch (\Exception) {
            $this->client->createIndex($indexName, ['primaryKey' => 'objectID']);
        }

        $index = $this->client->index($indexName);

        // Debug log the first object to check structure
        if (!empty($objects) && isset($objects[0])) {
            Mage::log('First document being indexed: ' . json_encode($objects[0]), null, 'meilisearch_debug.log');
        }

        // Meilisearch needs to know the primary key is 'objectID'
        $res = $index->addDocuments($objects, 'objectID');

        $this->lastUsedIndexName = $indexName;

        // Store the task object and extract UID
        $this->lastTask = $res;
        $taskUid = $this->extractTaskUid($res);
        $this->lastTaskId = $taskUid;

        return ['taskID' => $taskUid];
    }

    public function saveObjects($objects, $indexName)
    {
        return $this->addObjects($objects, $indexName);
    }

    public function waitLastTask()
    {
        if (!isset($this->lastUsedIndexName) || !isset($this->lastTask)) {
            return;
        }

        // Wait AND verify the task didn't fail. Previously this silently
        // swallowed rejections from Meilisearch (payload too large, vector
        // dimension mismatch, etc.) and the caller would move on as if the
        // documents were indexed.
        $result = $this->waitForTask($this->lastTask);
        $this->assertTaskSucceeded($result, "lastTask on index {$this->lastUsedIndexName}");
    }

    public function getIndexSettings($indexName)
    {
        $index = $this->client->index($indexName);
        return $index->getSettings();
    }

    public function copySynonyms($fromIndexName, $toIndexName)
    {
        $fromIndex = $this->client->index($fromIndexName);
        $toIndex = $this->client->index($toIndexName);

        $synonyms = $fromIndex->getSynonyms();
        if (!empty($synonyms)) {
            $toIndex->updateSynonyms($synonyms);
        }
    }

    /**
     * Convert search params to Meilisearch format
     */
    protected function convertSearchParams($params)
    {
        $meilisearchParams = [];

        if (isset($params['hitsPerPage'])) {
            $meilisearchParams['limit'] = $params['hitsPerPage'];
        }

        if (isset($params['page'])) {
            $meilisearchParams['offset'] = $params['page'] * ($params['hitsPerPage'] ?? 20);
        }

        if (isset($params['filters'])) {
            $meilisearchParams['filter'] = $this->convertFilters($params['filters']);
        }

        if (isset($params['facetFilters'])) {
            $meilisearchParams['filter'] = $this->convertFacetFilters($params['facetFilters']);
        }

        if (isset($params['numericFilters'])) {
            // Convert numeric filters to Meilisearch format
            $numericFilter = $this->convertNumericFilters($params['numericFilters']);
            if (isset($meilisearchParams['filter'])) {
                $meilisearchParams['filter'] .= ' AND ' . $numericFilter;
            } else {
                $meilisearchParams['filter'] = $numericFilter;
            }
        }

        if (isset($params['attributesToRetrieve'])) {
            // Ensure it's always an array
            if (is_string($params['attributesToRetrieve'])) {
                $meilisearchParams['attributesToRetrieve'] = [$params['attributesToRetrieve']];
            } else {
                $meilisearchParams['attributesToRetrieve'] = $params['attributesToRetrieve'];
            }
        }

        if (isset($params['attributesToHighlight']) && !empty($params['attributesToHighlight'])) {
            // Ensure it's always an array
            if (is_string($params['attributesToHighlight'])) {
                $meilisearchParams['attributesToHighlight'] = [$params['attributesToHighlight']];
            } else {
                $meilisearchParams['attributesToHighlight'] = $params['attributesToHighlight'];
            }
        }

        // Handle facets
        if (isset($params['facets']) && $params['facets'] === '*') {
            // Get all filterable attributes from settings
            try {
                $settings = $this->client->index($this->lastUsedIndexName)->getSettings();
                if (isset($settings['filterableAttributes'])) {
                    $meilisearchParams['facets'] = $settings['filterableAttributes'];
                }
            } catch (\Exception) {
                // Default to empty if we can't get settings
                $meilisearchParams['facets'] = [];
            }
        } elseif (isset($params['facets'])) {
            $meilisearchParams['facets'] = is_array($params['facets']) ? $params['facets'] : [$params['facets']];
        }

        // Handle sort
        if (isset($params['sort'])) {
            $meilisearchParams['sort'] = is_array($params['sort']) ? $params['sort'] : [$params['sort']];
        }

        // Handle attributesToRetrieve
        if (isset($params['attributesToRetrieve'])) {
            if (is_string($params['attributesToRetrieve']) && $params['attributesToRetrieve'] !== '') {
                $meilisearchParams['attributesToRetrieve'] = [$params['attributesToRetrieve']];
            } elseif (is_array($params['attributesToRetrieve'])) {
                $meilisearchParams['attributesToRetrieve'] = $params['attributesToRetrieve'];
            }
        }

        return $meilisearchParams;
    }

    /**
     * Convert Algolia numeric filters to Meilisearch format
     */
    protected function convertNumericFilters($numericFilters)
    {
        if (is_string($numericFilters)) {
            // Simple string like "visibility_search=1"
            return $numericFilters;
        }

        if (is_array($numericFilters)) {
            // Array of numeric filters
            return implode(' AND ', $numericFilters);
        }

        return '';
    }

    /**
     * Convert Algolia index settings to Meilisearch format
     */
    protected function convertIndexSettings($settings)
    {
        $meilisearchSettings = [];

        if (isset($settings['searchableAttributes'])) {
            // Remove "unordered()" prefix as Meilisearch doesn't support it
            $meilisearchSettings['searchableAttributes'] = array_map(fn($attr) => preg_replace('/^unordered\((.*)\)$/', '$1', (string) $attr), $settings['searchableAttributes']);
        }

        if (isset($settings['attributesForFaceting'])) {
            $meilisearchSettings['filterableAttributes'] = array_map(fn($attr) => str_replace('searchable(', '', str_replace(')', '', $attr)), $settings['attributesForFaceting']);
        }

        if (isset($settings['customRanking'])) {
            // Extract attributes for sortableAttributes
            $meilisearchSettings['sortableAttributes'] = array_map(fn($attr) => str_replace(['asc(', 'desc(', ')'], '', $attr), $settings['customRanking']);

            // Build custom ranking rules for Meilisearch
            $customRankingRules = [];
            foreach ($settings['customRanking'] as $ranking) {
                // Convert desc(ordered_qty) to ordered_qty:desc
                if (preg_match('/^(asc|desc)\(([^)]+)\)$/', (string) $ranking, $matches)) {
                    $customRankingRules[] = $matches[2] . ':' . $matches[1];
                }
            }

            // Set Meilisearch ranking rules with custom attributes at the end
            $meilisearchSettings['rankingRules'] = [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ];

            // Add custom ranking rules after the default rules
            foreach ($customRankingRules as $rule) {
                $meilisearchSettings['rankingRules'][] = $rule;
            }
        }

        // Handle rankingRules if directly provided (from Magento settings)
        if (isset($settings['rankingRules'])) {
            // Convert Algolia-style ranking rules to Meilisearch format
            $convertedRules = [];
            $sortableAttrs = [];

            foreach ($settings['rankingRules'] as $rule) {
                // Check if it's a custom ranking rule in Algolia format: desc(attribute) or asc(attribute)
                if (preg_match('/^(asc|desc)\(([^)]+)\)$/', (string) $rule, $matches)) {
                    // Convert to Meilisearch format: attribute:desc or attribute:asc
                    $convertedRules[] = $matches[2] . ':' . $matches[1];
                    $sortableAttrs[] = $matches[2];
                } else {
                    // Keep standard rules as-is (words, typo, proximity, etc.)
                    $convertedRules[] = $rule;
                }
            }

            $meilisearchSettings['rankingRules'] = $convertedRules;

            if (!empty($sortableAttrs)) {
                if (!isset($meilisearchSettings['sortableAttributes'])) {
                    $meilisearchSettings['sortableAttributes'] = [];
                }
                $meilisearchSettings['sortableAttributes'] = array_unique(array_merge(
                    $meilisearchSettings['sortableAttributes'],
                    $sortableAttrs,
                ));
            }
        }

        if (isset($settings['attributesToRetrieve'])) {
            $meilisearchSettings['displayedAttributes'] = $settings['attributesToRetrieve'];
        }

        if (isset($settings['displayedAttributes'])) {
            $meilisearchSettings['displayedAttributes'] = $settings['displayedAttributes'];
        }

        if (isset($settings['synonyms'])) {
            $meilisearchSettings['synonyms'] = $this->convertSynonyms($settings['synonyms']);
        }

        // Remove Algolia-specific settings that Meilisearch doesn't support
        unset($meilisearchSettings['replicas']);

        return $meilisearchSettings;
    }

    /**
     * Convert Algolia filters to Meilisearch format
     */
    protected function convertFilters($filters)
    {
        // This is a simplified conversion - may need to be enhanced based on actual usage
        return $filters;
    }

    /**
     * Convert Algolia facet filters to Meilisearch format
     */
    protected function convertFacetFilters($facetFilters)
    {
        $filters = [];

        foreach ($facetFilters as $filter) {
            if (is_array($filter)) {
                // OR condition
                $orFilters = [];
                foreach ($filter as $f) {
                    $orFilters[] = $this->parseFacetFilter($f);
                }
                $filters[] = '(' . implode(' OR ', $orFilters) . ')';
            } else {
                // Single filter
                $filters[] = $this->parseFacetFilter($filter);
            }
        }

        return implode(' AND ', $filters);
    }

    /**
     * Parse a single facet filter
     */
    protected function parseFacetFilter($filter)
    {
        if (str_contains((string) $filter, ':')) {
            [$attribute, $value] = explode(':', (string) $filter, 2);

            // Handle negative filters
            if (str_starts_with($attribute, '-')) {
                $attribute = substr($attribute, 1);
                return $attribute . ' != "' . $value . '"';
            }

            return $attribute . ' = "' . $value . '"';
        }

        return $filter;
    }

    /**
     * Set synonyms for an index
     */
    public function setSynonyms($indexName, $synonyms)
    {
        $index = $this->getIndex($indexName);

        if (!$index) {
            throw new Exception('Index not found: ' . $indexName);
        }

        // Convert synonyms to Meilisearch format
        $meilisearchSynonyms = $this->convertSynonyms($synonyms);

        // Update synonyms in index settings
        try {
            $index->updateSynonyms($meilisearchSynonyms);
        } catch (Exception $e) {
            Mage::logException($e);
            throw new Exception('Failed to update synonyms: ' . $e->getMessage());
        }
    }

    /**
     * Convert Algolia synonyms to Meilisearch format
     */
    protected function convertSynonyms($synonyms)
    {
        if (empty($synonyms)) {
            // meilisearch-php >=1.16 type-hints updateSynonyms($synonyms): array,
            // so an empty stdClass throws TypeError. Empty array serialises to
            // [] (vs {}) but Meilisearch accepts both as "no synonyms".
            return [];
        }

        $meilisearchSynonyms = [];

        foreach ($synonyms as $synonym) {
            if (isset($synonym['type']) && $synonym['type'] === 'oneWaySynonym') {
                $meilisearchSynonyms[$synonym['input']] = $synonym['synonyms'];
            } else {
                // Multi-way synonym
                $words = $synonym['synonyms'] ?? [];
                foreach ($words as $word) {
                    $others = array_filter($words, fn($w) => $w !== $word);
                    if (!empty($others)) {
                        $meilisearchSynonyms[$word] = array_values($others);
                    }
                }
            }
        }

        return empty($meilisearchSynonyms) ? new \stdClass() : $meilisearchSynonyms;
    }

    /**
     * Cast settings to proper types
     */
    protected function castSettings($settings)
    {
        if (isset($settings['hitsPerPage'])) {
            $settings['hitsPerPage'] = (int) $settings['hitsPerPage'];
        }

        if (isset($settings['maxValuesPerFacet'])) {
            $settings['maxValuesPerFacet'] = (int) $settings['maxValuesPerFacet'];
        }

        // Ensure synonyms is an object if empty
        if (isset($settings['synonyms']) && is_array($settings['synonyms']) && empty($settings['synonyms'])) {
            $settings['synonyms'] = new \stdClass();
        }

        return $settings;
    }
}
