<?php

/**
 * Meilisearch_Search FAQ entity helper.
 *
 * Reads from Mageaustralia_Faq tables and respects per-store-view filtering via the
 * mageaustralia_faq_category_store / mageaustralia_faq_store join tables.
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2026 Mageaustralia (https://mageaustralia.com.au)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License 3.0
 */
class Meilisearch_Search_Helper_Entity_Faqhelper extends Meilisearch_Search_Helper_Entity_Helper
{
    protected function getIndexNameSuffix()
    {
        return '_faqs';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(category)', 'unordered(question)', 'unordered(answer)'],
            'attributesToSnippet'  => ['answer:10'],
            'displayedAttributes'  => ['objectID', 'category', 'category_url', 'question', 'answer', 'url'],
        ];

        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_faqs_index_before_set_settings', [
            'store_id'       => $storeId,
            'index_settings' => $transport,
        ]);

        return $transport->getData();
    }

    /**
     * @param int|string|null $storeId
     * @param array<int, int>|null $faqIds
     * @return array<int, array<string, mixed>>
     */
    public function getFaqs($storeId, $faqIds = null)
    {
        // Defensive: every reindex entry point already guards via
        // Engine::rebuildFaqs(), but a queued rebuildFaqIndex job can still run
        // after the module is disabled/uninstalled. Without this, getModel()
        // returns false and the Mageaustralia_Faq_Model_Status constant below is
        // undefined -> fatal in the queue runner. Return nothing instead.
        if (!Mage::helper('core')->isModuleEnabled('Mageaustralia_Faq')) {
            return [];
        }

        $storeId = (int) $storeId;

        /** @var Mageaustralia_Faq_Model_Resource_Category_Collection $categories */
        $categories = Mage::getModel('mageaustralia_faq/category')->getCollection();
        $categories->addStoreFilter($storeId)
            ->addFieldToFilter('status', Mageaustralia_Faq_Model_Status::STATUS_ENABLED)
            ->setOrder('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);

        $cmsHelper = Mage::helper('cms');
        $tmplProc  = $cmsHelper->getPageTemplateProcessor();

        $faqs = [];
        /** @var Mageaustralia_Faq_Model_Category $category */
        foreach ($categories as $category) {
            /** @var Mageaustralia_Faq_Model_Resource_Faq_Collection $faqCollection */
            $faqCollection = Mage::getModel('mageaustralia_faq/faq')->getCollection();
            $faqCollection->addStoreFilter($storeId)
                ->addFieldToFilter('status', Mageaustralia_Faq_Model_Status::STATUS_ENABLED)
                ->addFieldToFilter('category_id', (int) $category->getId())
                ->setOrder('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);

            if ($faqIds && count($faqIds) > 0) {
                $faqCollection->addFieldToFilter('entity_id', ['in' => array_map('intval', $faqIds)]);
            }

            /** @var Mageaustralia_Faq_Model_Faq $faq */
            foreach ($faqCollection as $faq) {
                $answer = $tmplProc->filter((string) $faq->getContent());
                $answer = $this->strip($answer, ['script', 'style']);

                $urlKey = (string) $faq->getUrlKey();
                $slug   = $urlKey !== '' ? $urlKey : Mage::helper('mageaustralia_faq')->slugify((string) $faq->getTitle());

                $faqObject = [
                    'objectID'     => 'faq_' . (int) $faq->getId(),
                    'category'     => (string) $category->getName(),
                    'category_url' => (string) $category->getUrlKey(),
                    'question'     => (string) $faq->getTitle(),
                    'answer'       => $answer,
                    // Trailing slash before #: without it the server 301s
                    // /faq/<cat> → /faq/<cat>/ and Chrome drops the fragment
                    // during the redirect (the Location header has no #anchor
                    // for the browser to preserve), so the accordion never opens.
                    'url'          => Mage::getBaseUrl() . 'faq/' . $category->getUrlKey() . '/#' . $slug,
                ];

                $transport = new Varien_Object($faqObject);
                Mage::dispatchEvent('meilisearch_after_create_faq_object', [
                    'faq'       => $transport,
                    'faqObject' => $faq,
                ]);

                $faqs[] = $transport->getData();
            }
        }

        return $faqs;
    }

    public function shouldIndexFaqs($storeId)
    {
        $autocompleteSections = $this->config->getAutocompleteSections($storeId);
        foreach ($autocompleteSections as $section) {
            if ($section['name'] === 'faqs') {
                return true;
            }
        }
        return false;
    }
}
