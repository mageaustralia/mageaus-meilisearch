<?php

/**
 * Meilisearch search observer model.
 */
class Meilisearch_Search_Model_Observer
{
    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Helper_Entity_Producthelper */
    protected $product_helper;

    /** @var Meilisearch_Search_Helper_Entity_Categoryhelper **/
    protected $category_helper;

    /** @var Meilisearch_Search_Helper_Entity_Suggestionhelper */
    protected $suggestion_helper;

    /** @var Meilisearch_Search_Helper_Data */
    protected $helper;

    public function __construct()
    {
        $this->config = Mage::helper('meilisearch_search/config');
        $this->product_helper = Mage::helper('meilisearch_search/entity_producthelper');
        $this->category_helper = Mage::helper('meilisearch_search/entity_categoryhelper');
        $this->suggestion_helper = Mage::helper('meilisearch_search/entity_suggestionhelper');
        $this->helper = Mage::helper('meilisearch_search');
    }

    /**
     * On configuration save
     */
    public function configSaved(Varien_Event_Observer $observer)
    {
        try {
            $this->saveSettings();
        } catch (\Exception $e) {
            // Let the exception propagate so the admin can see the error
            throw $e;
        }
    }

    public function saveSettings($isFullProductReindex = false)
    {
        if (is_object($isFullProductReindex) && $isFullProductReindex::class === 'Varien_Object') {
            $eventData = $isFullProductReindex->getData();
            $isFullProductReindex = $eventData['isFullProductReindex'];
        }

        foreach (Mage::app()->getStores() as $store) {/* @var $store Mage_Core_Model_Store */
            if ($store->getIsActive()) {
                $saveToTmpIndicesToo = ($isFullProductReindex && $this->config->isQueueActive($store->getId()));
                $this->helper->saveConfigurationToMeilisearch($store->getId(), $saveToTmpIndicesToo);
            }
        }
    }

    public function addBundleToAdmin(Varien_Event_Observer $observer)
    {
        $req = Mage::app()->getRequest();

        if (str_contains($req->getPathInfo(), 'system_config/edit/section/meilisearch')) {
            $observer->getData('layout')->getUpdate()->addHandle('meilisearch_bundle_handle');
        }
    }

    /**
     * Call meilisearch.xml to load JS / CSS / PHTMLs
     *
     * @return $this
     */
    public function useMeilisearchSearchPopup(Varien_Event_Observer $observer)
    {
        if (!$this->config->isEnabledFrontEnd()) {
            return $this;
        }

        $storeId = Mage::app()->getStore()->getId();
        if (!$this->config->getServerUrl($storeId) || !$this->config->getAPIKey($storeId)) {
            return $this;
        }

        $this->loadMeilisearchSearchHandle($observer);

        $this->loadSearchFormHandle($observer);

        $this->loadPreventBackendRenderingHandle($observer);

        return $this;
    }

    public function saveProduct(Varien_Event_Observer $observer)
    {
        if ($this->isIndexerInManualMode('meilisearch_search_indexer')) {
            return;
        }

        $product = $observer->getDataObject();
        $product = Mage::getModel('catalog/product')->load($product->getId());

        Meilisearch_Search_Model_Indexer_Meilisearch::$product_categories[$product->getId()] = $product->getCategoryIds();
    }

    /**
     * @event cms_page_save_commit_after
     */
    public function savePage(Varien_Event_Observer $observer)
    {
        if (!$this->config->getServerUrl()
            || !$this->config->getAPIKey()
            || $this->isIndexerInManualMode('meilisearch_search_indexer_pages')) {
            return;
        }

        /** @var Mage_Cms_Model_Page $page */
        $page = $observer->getEvent()->getDataObject();
        $storeIds = $page->getStores();

        /** @var Meilisearch_Search_Model_Resource_Engine $engine */
        $engine = Mage::getResourceModel('meilisearch_search/engine');

        foreach ($storeIds as $storeId) {
            if ($storeId == 0) {
                $storeId = null;
            }
            $engine->rebuildPages($storeId, [$page->getPageId()]);
        }
    }

    public function deleteProductsStoreIndices(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $this->helper->deleteProductsStoreIndices($storeId);
    }

    public function deleteCategoriesStoreIndices(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $this->helper->deleteCategoriesStoreIndices($storeId);
    }

    public function removeCategories(Varien_Object $event)
    {
        $storeId = $event->getStoreId();
        $category_ids = $event->getCategoryIds();

        $this->helper->removeCategories($category_ids, $storeId);
    }

    public function rebuildAdditionalSectionsIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $this->helper->rebuildStoreAdditionalSectionsIndex($storeId);
    }

    public function rebuildPageIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();
        $pageIds = $event->getPageIds();

        $this->helper->rebuildStorePageIndex($storeId, $pageIds);
    }

    public function rebuildSuggestionIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $page = $event->getPage();
        $pageSize = $event->getPageSize();

        if (is_null($storeId) && !empty($categoryIds)) {
            foreach (Mage::app()->getStores() as $storeId => $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $this->helper->rebuildStoreSuggestionIndex($storeId);
            }
        } else {
            if (!empty($page) && !empty($pageSize)) {
                $this->helper->rebuildStoreSuggestionIndexPage(
                    $storeId,
                    $this->suggestion_helper->getSuggestionCollectionQuery($storeId),
                    $page,
                    $pageSize,
                );
            } else {
                $this->helper->rebuildStoreSuggestionIndex($storeId);
            }
        }

        return $this;
    }

    public function moveStoreSuggestionIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $this->helper->moveStoreSuggestionIndex($storeId);
    }

    public function rebuildCategoryIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();
        $categoryIds = $event->getCategoryIds();

        $page = $event->getPage();
        $pageSize = $event->getPageSize();

        if (is_null($storeId) && !empty($categoryIds)) {
            foreach (Mage::app()->getStores() as $storeId => $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $this->helper->rebuildStoreCategoryIndex($storeId, $categoryIds);
            }
        } else {
            if (!empty($page) && !empty($pageSize)) {
                $this->helper->rebuildStoreCategoryIndexPage(
                    $storeId,
                    $this->category_helper->getCategoryCollectionQuery($storeId, $categoryIds),
                    $page,
                    $pageSize,
                );
            } else {
                $this->helper->rebuildStoreCategoryIndex($storeId, $categoryIds);
            }
        }

        return $this;
    }

    public function rebuildProductIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();
        $productIds = $event->getProductIds();

        $page = $event->getPage();
        $pageSize = $event->getPageSize();

        $useTmpIndex = (bool) $event->getUseTmpIndex();

        if (is_null($storeId) && !empty($productIds)) {
            foreach (Mage::app()->getStores() as $storeId => $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $this->helper->rebuildStoreProductIndex($storeId, $productIds);
            }
        } else {
            if (!empty($page) && !empty($pageSize)) {
                $collection = $this->product_helper->getProductCollectionQuery($storeId, $productIds, $useTmpIndex);
                $this->helper->rebuildStoreProductIndexPage($storeId, $collection, $page, $pageSize, null, $productIds, $useTmpIndex);
            } else {
                $this->helper->rebuildStoreProductIndex($storeId, $productIds);
            }
        }

        return $this;
    }

    public function moveProductsTmpIndex(Varien_Object $event)
    {
        $storeId = $event->getStoreId();

        $this->helper->moveProductsIndex($storeId);
    }

    private function loadMeilisearchSearchHandle(Varien_Event_Observer $observer)
    {
        if (!$this->config->isPopupEnabled() && !$this->config->isInstantEnabled() && !$this->config->isAutoCompleteEnabled()) {
            return;
        }

        $observer->getData('layout')->getUpdate()->addHandle('meilisearch_search_handle');
    }

    private function loadSearchFormHandle(Varien_Event_Observer $observer)
    {
        if (!$this->config->isDefaultSelector()) {
            return;
        }

        $observer->getData('layout')->getUpdate()->addHandle('meilisearch_search_handle_with_topsearch');
    }

    private function loadPreventBackendRenderingHandle(Varien_Event_Observer $observer)
    {
        if (!$this->config->preventBackendRendering()) {
            return;
        }

        $category = Mage::registry('current_category');
        $backendRenderingDisplayMode = $this->config->getBackendRenderingDisplayMode();
        if ($category && $backendRenderingDisplayMode === 'only_products' && $category->getDisplayMode() === 'PAGE') {
            return;
        }

        $observer->getData('layout')->getUpdate() ->addHandle('meilisearch_search_handle_prevent_backend_rendering');
    }

    private function isIndexerInManualMode($indexerCode)
    {
        /** @var $process Mage_Index_Model_Process */
        $process = Mage::getModel('index/process')->load($indexerCode, 'indexer_code');
        if (!is_null($process) && $process->getMode() == Mage_Index_Model_Process::MODE_MANUAL) {
            return true;
        }

        return false;
    }

    /**
     * Cron: clean junk queries then rebuild suggestions index for all active stores.
     * Runs daily at 3am via crontab config.
     */
    public function cronRebuildSuggestions()
    {
        if (!$this->config->isEnabledBackend()) {
            return;
        }

        $this->cleanJunkSearchQueries();

        /** @var Meilisearch_Search_Helper_Data $helper */
        $helper = Mage::helper('meilisearch_search');

        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if (!$this->config->isEnabledBackend($store->getId())) {
                continue;
            }

            try {
                $helper->rebuildStoreSuggestionIndex($store->getId());
                // Wait for Meilisearch to finish indexing before swapping
                sleep(3);
                $helper->moveStoreSuggestionIndex($store->getId());
                Mage::log(
                    'Meilisearch: suggestions rebuilt for store ' . $store->getCode(),
                    6,
                    'meilisearch.log',
                );
            } catch (\Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Remove junk/bot search queries from catalogsearch_query.
     *
     * Cleans: URLs, SQL injection attempts, HTML/script tags, encoded queries (+),
     * excessively long queries, promo text, and very low popularity garbage.
     */
    private function cleanJunkSearchQueries(): void
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalogsearch/search_query');

        $deleted = 0;

        // URLs and spam
        $deleted += $write->delete($table, "query_text LIKE '%http%' OR query_text LIKE '%www.%'");

        // Script injection attempts
        $deleted += $write->delete($table, "query_text LIKE '%<script%' OR query_text LIKE '%</%'");

        // SQL injection patterns
        $deleted += $write->delete($table, "query_text LIKE '%SELECT %' OR query_text LIKE '%UNION %' OR query_text LIKE '%DROP %'");

        // Encoded queries (Magento URL artifacts with +)
        $deleted += $write->delete($table, "query_text LIKE '%+%'");

        // Quoted queries (not useful for suggestions)
        $deleted += $write->delete($table, "query_text LIKE '\"%' OR query_text LIKE '%\"'");

        // Excessively long queries (> 60 chars — real searches are shorter)
        $deleted += $write->delete($table, 'LENGTH(query_text) > 60');

        // Very short queries (1-2 chars — likely typos/bots)
        $deleted += $write->delete($table, 'LENGTH(query_text) < 3');

        // Queries with 0 results and low popularity (dead ends)
        $deleted += $write->delete($table, 'num_results = 0 AND popularity < 10');

        // Promo text fragments
        $deleted += $write->delete($table, "query_text LIKE '%Buy %' OR query_text LIKE '%Free!%' OR query_text LIKE '%OFF code%'");

        if ($deleted > 0) {
            Mage::log(
                "Meilisearch: cleaned {$deleted} junk search queries",
                6,
                'meilisearch.log',
            );
        }
    }
}
