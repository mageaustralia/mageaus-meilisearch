<?php

class Meilisearch_Search_Helper_Entity_Amastypagehelper extends Meilisearch_Search_Helper_Entity_Helper
{
    protected function getIndexNameSuffix()
    {
        return '_amasty_pages';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['slug', 'name', 'content'],
            'displayedAttributes' => ['objectID', 'slug', 'name', 'url', 'content'],
            'attributesToHighlight' => ['name', 'content'],
            'attributesToCrop' => ['content:10'],
        ];

        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_pages_index_before_set_settings', ['store_id' => $storeId, 'index_settings' => $transport]);
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getAmastyPages($storeId)
    {
        /** @var Amasty_Shopby_Model_Resource_Page_Collection $pageCollection */
        $pageCollection = Mage::getModel('amshopby/page')->getCollection();

        // Debug: Log count before store filter
        Mage::log('Amasty pages collection count BEFORE store filter: ' . $pageCollection->count(), null, 'meilisearch_debug.log');
        Mage::log('Store ID for filter: ' . $storeId, null, 'meilisearch_debug.log');

        $pageCollection->addStoreFilter($storeId);

        // Debug: Log count after store filter
        Mage::log('Amasty pages collection count AFTER store filter: ' . $pageCollection->count(), null, 'meilisearch_debug.log');

        // Debug: Log the SQL query
        Mage::log('Amasty pages SQL query: ' . $pageCollection->getSelect()->__toString(), null, 'meilisearch_debug.log');

        Mage::dispatchEvent('meilisearch_after_amasty_pages_collection_build', ['store' => $storeId, 'collection' => $pageCollection]);

        $pages = [];
        $seenTitles = []; // Track titles we've already processed

        // Debug: Log collection size and check for limit
        Mage::log('Collection size before iteration: ' . $pageCollection->getSize(), null, 'meilisearch_debug.log');
        Mage::log('Collection count method: ' . count($pageCollection), null, 'meilisearch_debug.log');

        // Check if there's a limit set
        $select = $pageCollection->getSelect();
        $limitCount = $select->getPart(\Maho\Db\Select::LIMIT_COUNT);
        $limitOffset = $select->getPart(\Maho\Db\Select::LIMIT_OFFSET);
        Mage::log('Limit count: ' . var_export($limitCount, true) . ', Offset: ' . var_export($limitOffset, true), null, 'meilisearch_debug.log');

        $pageCount = 0;
        $skippedCount = 0;
        foreach ($pageCollection as $page) {
            $pageCount++;

            // Skip if we've already seen this title
            $title = $page->getTitle();
            if (isset($seenTitles[$title])) {
                $skippedCount++;
                Mage::log('Skipping duplicate page ID ' . $page->getId() . ' with title: ' . $title . ' (already have ID ' . $seenTitles[$title] . ')', null, 'meilisearch_debug.log');
                continue;
            }
            $seenTitles[$title] = $page->getId();
            $pageObject = [];

            $path = parse_url((string) $page->getUrl(), PHP_URL_PATH);

            $pageObject['slug'] = $path;
            $pageObject['name'] = $page->getTitle();

            $content = $page->getDescription();

            $pageObject['objectID'] = $page->getId();
            $pageObject['url'] = $page->getUrl();
            $pageObject['content'] = $this->strip($content, ['script', 'style']);

            $transport = new Varien_Object($pageObject);
            Mage::dispatchEvent('meilisearch_after_create_amasty_page_object', ['page' => $transport, 'pageObject' => $page]);
            $pageObject = $transport->getData();

            $pages[] = $pageObject;
        }

        // Debug: Log final count
        Mage::log('Total pages iterated: ' . $pageCount, null, 'meilisearch_debug.log');
        Mage::log('Duplicates skipped: ' . $skippedCount, null, 'meilisearch_debug.log');
        Mage::log('Total unique pages in array: ' . count($pages), null, 'meilisearch_debug.log');

        return $pages;
    }

    public function shouldIndexPages($storeId)
    {
        // Check if Amasty Shopby module is enabled
        if (!Mage::helper('core')->isModuleEnabled('Amasty_Shopby')) {
            return false;
        }

        $autocompleteSections = $this->config->getAutocompleteSections($storeId);

        // Always return true if Amasty Shopby is enabled
        // The admin can control this via the autocomplete sections config
        return true;
    }

    public function getObject(Varien_Object $page)
    {
        $pageObject = [];

        $path = parse_url($page->getUrl(), PHP_URL_PATH);

        $pageObject['slug'] = $path;
        $pageObject['name'] = $page->getTitle();
        $pageObject['objectID'] = $page->getId();
        $pageObject['url'] = $page->getUrl();
        $pageObject['content'] = $this->strip($page->getDescription(), ['script', 'style']);

        $transport = new Varien_Object($pageObject);
        Mage::dispatchEvent('meilisearch_after_create_amasty_page_object', ['page' => $transport, 'pageObject' => $page]);

        return $transport->getData();
    }
}
