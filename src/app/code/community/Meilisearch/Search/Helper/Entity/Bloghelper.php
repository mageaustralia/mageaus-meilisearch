<?php

/**
 * Blog post entity helper. Indexes Maho_Blog posts into a per-store
 * `<prefix>_<store>_blog` index so the Meilisearch autocomplete can
 * surface them alongside Pages and Categories.
 *
 * Active only when the Maho_Blog module is enabled - Helper instantiation
 * still works without the module (the helper class itself doesn't depend
 * on Maho_Blog), but every method becomes a no-op so callers can ask
 * "would you index?" without having to feature-detect upstream.
 */
class Meilisearch_Search_Helper_Entity_Bloghelper extends Meilisearch_Search_Helper_Entity_Helper
{
    protected function getIndexNameSuffix()
    {
        return '_blog';
    }

    public function isBlogModuleEnabled(): bool
    {
        return Mage::helper('core')->isModuleEnabled('Maho_Blog');
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(title)', 'unordered(url_key)', 'unordered(content)'],
            'attributesToSnippet'  => ['content:7'],
        ];

        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_blog_index_before_set_settings', ['store_id' => $storeId, 'index_settings' => $transport]);

        return $transport->getData();
    }

    /**
     * Build the per-post documents. Returns [] when Maho_Blog isn't
     * installed/enabled so the indexer can call this unconditionally.
     */
    public function getPosts($storeId, $postIds = null)
    {
        if (!$this->isBlogModuleEnabled()) {
            return [];
        }

        /** @var Mage_Core_Model_Resource_Iterator $collection */
        $collection = Mage::getModel('blog/post')->getCollection()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        // Only past-dated posts (drafts scheduled for the future shouldn't
        // surface in search). Maho_Blog stores publish_date as DATE, so
        // CURDATE() is the right comparison.
        $collection->addFieldToFilter('publish_date', ['lteq' => date('Y-m-d')]);

        if ($postIds && count($postIds) > 0) {
            $collection->addFieldToFilter('entity_id', ['in' => $postIds]);
        }

        Mage::dispatchEvent('meilisearch_after_blog_collection_build', ['store' => $storeId, 'collection' => $collection]);

        $posts = [];
        foreach ($collection as $post) {
            $object = [
                'objectID'    => (int) $post->getId(),
                'title'       => (string) $post->getTitle(),
                'url_key'     => (string) $post->getUrlKey(),
                'url'         => (string) $post->getUrl(),
                'content'     => $this->strip((string) $post->getContent(), ['script', 'style']),
                'publish_date' => (string) $post->getPublishDate(),
            ];

            // Optional thumbnail, when the post has an `image` attribute.
            $image = (string) $post->getImage();
            if ($image !== '' && $image !== 'no_selection') {
                $object['image_url'] = rtrim(Mage::getBaseUrl('media'), '/') . '/blog/post/image/' . ltrim($image, '/');
            }

            $transport = new Varien_Object($object);
            Mage::dispatchEvent('meilisearch_after_create_blog_post_object', ['post_object' => $transport, 'post' => $post]);
            $posts[] = $transport->getData();
        }

        return $posts;
    }

    /**
     * Whether to index the blog index for autocomplete. Off by default;
     * an admin opts in by setting the suggestion count above 0 OR by
     * adding a `blog` entry to the autocomplete sections list.
     */
    public function shouldIndexBlog($storeId): bool
    {
        if (!$this->isBlogModuleEnabled()) {
            return false;
        }

        $count = (int) $this->config->getNumberOfBlogSuggestions($storeId);
        if ($count > 0) {
            return true;
        }

        // Power-user opt-in via the same mechanism the Pages section uses.
        foreach ($this->config->getAutocompleteSections($storeId) as $section) {
            if (($section['name'] ?? '') === 'blog') {
                return true;
            }
        }

        return false;
    }
}
