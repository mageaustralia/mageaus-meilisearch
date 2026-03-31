<?php

class Meilisearch_Search_Helper_Entity_Pagehelper extends Meilisearch_Search_Helper_Entity_Helper
{
    protected function getIndexNameSuffix()
    {
        return '_pages';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(slug)', 'unordered(name)', 'unordered(content)'],
            'attributesToSnippet'  => ['content:7'],
        ];

        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_pages_index_before_set_settings', ['store_id' => $storeId, 'index_settings' => $transport]);
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getPages($storeId, $pageIds = null)
    {
        /** @var Mage_Cms_Model_Resource_Page_Collection $pages */
        $pageCollection = Mage::getModel('cms/page')->getCollection()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        if ($pageIds && count($pageIds) > 0) {
            $pageCollection->addFieldToFilter('page_id', ['in' => $pageIds]);
        }

        Mage::dispatchEvent('meilisearch_after_pages_collection_build', ['store' => $storeId, 'collection' => $pageCollection]);

        $excludedPages = array_values($this->config->getExcludedPages());
        foreach ($excludedPages as &$excludedPage) {
            $excludedPage = $excludedPage['pages'];
        }

        $pages = [];
        /** @var Mage_Cms_Model_Page $page */
        foreach ($pageCollection as $page) {
            if (in_array($page->getIdentifier(), $excludedPages)) {
                continue;
            }

            $pageObject = [];

            $pageObject['slug'] = $page->getIdentifier();
            $pageObject['name'] = $page->getTitle();

            $content = $page->getContent();
            if ($this->config->getRenderTemplateDirectives()) {
                /** @var Mage_Cms_Helper_Data $cms_helper */
                $cms_helper = Mage::helper('cms');
                $tmplProc = $cms_helper->getPageTemplateProcessor();
                $content = $tmplProc->filter($content);
            }

            /** @var Mage_Cms_Helper_Page $cmsPageHelper */
            $cmsPageHelper = Mage::helper('cms/page');

            $pageObject['objectID'] = $page->getId();
            $pageObject['url'] = $cmsPageHelper->getPageUrl($page->getId());
            $pageObject['content'] = $this->strip($content, ['script', 'style']);

            $transport = new Varien_Object($pageObject);
            Mage::dispatchEvent('meilisearch_after_create_page_object', ['page' => $transport, 'pageObject' => $page]);
            $pageObject = $transport->getData();

            $pages[] = $pageObject;
        }

        return $pages;
    }

    public function shouldIndexPages($storeId)
    {
        $autocompleteSections = $this->config->getAutocompleteSections($storeId);

        foreach ($autocompleteSections as $section) {
            if ($section['name'] === 'pages') {
                return true;
            }
        }

        return false;
    }
}
