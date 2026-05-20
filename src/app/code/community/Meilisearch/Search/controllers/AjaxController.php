<?php

/**
 * 2025 Maho
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@maho.org so we can send you a copy immediately.
 *
 * @category   Meilisearch
 * @package    Meilisearch_Search
 * @copyright  Copyright (c) 2025 Maho (https://www.maho.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Meilisearch_Search_AjaxController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get form key for AJAX requests
     */
    #[\Maho\Config\Route('/msearchtrack/ajax/getformkey', name: 'msearchtrack.ajax.getformkey')]
    #[\Maho\Config\Route('/meilisearch/ajax/getformkey', name: 'meilisearch.ajax.getformkey')]
    public function getformkeyAction()
    {
        $formKey = Mage::getSingleton('core/session')->getFormKey();

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode(['formKey' => $formKey]));
    }

    /**
     * Track search click-through: query + clicked product/category.
     * POST: { query, type, objectID, name, position }
     */
    #[\Maho\Config\Route('/msearchtrack/ajax/trackclick', name: 'msearchtrack.ajax.trackclick')]
    #[\Maho\Config\Route('/meilisearch/ajax/trackclick', name: 'meilisearch.ajax.trackclick')]
    public function trackclickAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405);
            return;
        }

        try {
            $body = json_decode($this->getRequest()->getRawBody(), true);
            if (!$body || empty($body['query'])) {
                $this->getResponse()->setHttpResponseCode(400);
                return;
            }

            $query    = mb_substr(trim($body['query']), 0, 128);
            $allowedTypes = ['product', 'category', 'page', 'blog', 'suggestion'];
            $type     = in_array($body['type'] ?? '', $allowedTypes, true) ? $body['type'] : 'product';
            $objectID = $body['objectID'] ?? $body['object_id'] ?? null;
            $name     = mb_substr(trim($body['name'] ?? ''), 0, 255);
            $position = (int) ($body['position'] ?? 0);
            $storeId  = (int) Mage::app()->getStore()->getId();

            $resource = Mage::getSingleton('core/resource');
            $write = $resource->getConnection('core_write');
            $table = $resource->getTableName('meilisearch_search_clicks');

            // Ensure table exists (created lazily)
            if (!$write->isTableExists($table)) {
                $ddl = $write->newTable($table)
                    ->addColumn('click_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
                        'identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true,
                    ])
                    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false])
                    ->addColumn('query', Varien_Db_Ddl_Table::TYPE_TEXT, 128, ['nullable' => false])
                    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_TEXT, 20, ['nullable' => false, 'default' => 'product'])
                    ->addColumn('object_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true])
                    ->addColumn('object_name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, ['nullable' => true])
                    ->addColumn('position', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
                    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
                    ->addIndex($write->getIndexName($table, ['store_id', 'query']), ['store_id', 'query'])
                    ->addIndex($write->getIndexName($table, ['object_id']), ['object_id'])
                    ->setComment('Meilisearch Search Click-Through Analytics');
                $write->createTable($ddl);
            }

            $write->insert($table, [
                'store_id'    => $storeId,
                'query'       => $query,
                'type'        => $type,
                'object_id'   => ($objectID !== null && is_numeric($objectID)) ? (int) $objectID : null,
                'object_name' => $name ?: null,
                'position'    => $position,
            ]);

            // Also update catalogsearch_query popularity (upsert via canonical model)
            /** @var Mage_CatalogSearch_Model_Query $searchQuery */
            $searchQuery = Mage::getModel('catalogsearch/query');
            $searchQuery->setStoreId($storeId);
            $searchQuery->loadByQuery($query);
            if (!$searchQuery->getId()) {
                $searchQuery->setQueryText($query)
                    ->setStoreId($storeId)
                    ->setNumResults(0)
                    ->setPopularity(0);
            }
            $searchQuery->setPopularity((int) $searchQuery->getPopularity() + 1)
                ->setUpdatedAt(Mage::getModel('core/date')->gmtDate())
                ->save();

            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody('{"ok":true}');
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }
}
