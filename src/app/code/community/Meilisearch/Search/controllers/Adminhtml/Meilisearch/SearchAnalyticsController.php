<?php

class Meilisearch_Search_Adminhtml_Meilisearch_SearchAnalyticsController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_title('Meilisearch')->_title('Search Analytics');
        $this->loadLayout();
        $this->_setActiveMenu('system/meilisearch/search_analytics');

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $clicksTable = $resource->getTableName('meilisearch_search_clicks');
        $queryTable = $resource->getTableName('catalogsearch/search_query');

        $hasClicksTable = $read->isTableExists($clicksTable);

        // Top clicked queries (last 30 days)
        $topQueries = [];
        if ($hasClicksTable) {
            $topQueries = $read->fetchAll(
                $read->select()
                    ->from($clicksTable, ['query', 'clicks' => new Maho\Db\Expr('COUNT(*)')])
                    ->where('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->group('query')
                    ->order('clicks DESC')
                    ->limit(20),
            );
        }

        // Top clicked products (last 30 days)
        $topProducts = [];
        if ($hasClicksTable) {
            $topProducts = $read->fetchAll(
                $read->select()
                    ->from($clicksTable, [
                        'object_id',
                        'object_name' => new Maho\Db\Expr('MAX(object_name)'),
                        'clicks' => new Maho\Db\Expr('COUNT(*)'),
                        'queries' => new Maho\Db\Expr('COUNT(DISTINCT query)'),
                    ])
                    ->where('type = ?', 'product')
                    ->where('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->group('object_id')
                    ->order('clicks DESC')
                    ->limit(20),
            );
        }

        // Popular searches (from catalogsearch_query, all time)
        $popularSearches = $read->fetchAll(
            $read->select()
                ->from($queryTable, ['query_text', 'num_results', 'popularity'])
                ->where('popularity >= ?', 3)
                ->where('num_results >= ?', 1)
                ->where("query_text != '__empty__'")
                ->where('LENGTH(query_text) >= 3')
                ->order('popularity DESC')
                ->limit(30),
        );

        // Zero-result queries (last 30 days from clicks, or from catalogsearch_query)
        $zeroResults = $read->fetchAll(
            $read->select()
                ->from($queryTable, ['query_text', 'popularity', 'updated_at'])
                ->where('num_results = 0')
                ->where('popularity >= ?', 2)
                ->order('popularity DESC')
                ->limit(20),
        );

        // Recent clicks (last 50)
        $recentClicks = [];
        if ($hasClicksTable) {
            $recentClicks = $read->fetchAll(
                $read->select()
                    ->from($clicksTable)
                    ->order('created_at DESC')
                    ->limit(50),
            );
        }

        // Build HTML
        $block = $this->getLayout()->createBlock('adminhtml/template')
            ->setTemplate('meilisearch/analytics.phtml')
            ->setData('top_queries', $topQueries)
            ->setData('top_products', $topProducts)
            ->setData('popular_searches', $popularSearches)
            ->setData('zero_results', $zeroResults)
            ->setData('recent_clicks', $recentClicks)
            ->setData('has_clicks_table', $hasClicksTable);

        $this->_addContent($block);
        $this->renderLayout();
    }

    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch_search');
    }
}
