<?php

class Meilisearch_Search_Helper_IndexChecker extends Mage_Core_Helper_Abstract
{
    /** @var Meilisearch_Search_Helper_Config */
    private $configHelper;

    /** @var Varien_Db_Adapter_Interface */
    private $dbConnection;

    /** @var array */
    private $pendingProductIds;

    public function __construct()
    {
        /** @var Meilisearch_Search_Helper_Config configHelper */
        $this->configHelper = Mage::helper('meilisearch_search/config');
    }

    /**
     * @param $storeId
     * @param $productIds
     * @throws Exception
     */
    public function checkIndexers($storeId, $productIds)
    {
        if ($this->configHelper->isQueueActive($storeId) === false) {
            return;
        }

        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }

        if (empty($productIds)) {
            return;
        }

        $pendingProductIds = $this->getPendingProductIds($storeId);

        if (empty($pendingProductIds)) {
            return;
        }

        foreach ($productIds as $id) {
            if (isset($this->pendingProductIds[$id])) {
                // Throw an exception - this exception is caught in Queue class and it'll retry next time Meilisearch is processed.
                throw new Meilisearch_Search_Model_Exception_IndexPendingException('Reindexing (price / stock) is still pending for entity ID ' . $id);
            }
        }
    }

    private function getPendingProductIds($storeId)
    {
        if (isset($this->pendingProductIds) === false) {
            $priceIndexes = $this->pendingPriceIndex($storeId);
            $stockIndexes = $this->pendingStockIndex($storeId);

            $pendingProductIds = array_unique(array_merge($priceIndexes, $stockIndexes));
            $this->pendingProductIds = array_flip($pendingProductIds);
        }

        return $this->pendingProductIds;
    }

    /**
     * Find all productId's pending a price reindex.
     *
     * @param $storeId
     * @return array
     */
    private function pendingPriceIndex($storeId)
    {
        $returnArray = [];

        if ($this->configHelper->shouldCheckPriceIndex($storeId)) {
            $maxVersion = $this->findLatestVersion($this->checkTablePrefix('catalog_product_index_price_cl'));
            $returnArray = $this->findProductIdsPending($this->checkTablePrefix('catalog_product_index_price_cl'), 'entity_id', $maxVersion);
        }

        return $returnArray;
    }

    /**
     * Find all productId's pending a stock reindex.
     *
     * @param $storeId
     * @return array
     */
    private function pendingStockIndex($storeId)
    {
        $returnArray = [];

        if ($this->configHelper->shouldCheckStockIndex($storeId)) {
            $maxVersion = $this->findLatestVersion($this->checkTablePrefix('cataloginventory_stock_status_cl'));
            $returnArray = $this->findProductIdsPending($this->checkTablePrefix('cataloginventory_stock_status_cl'), 'product_id', $maxVersion);
        }

        return $returnArray;
    }

    /**
     * Find all product ID's who have a version ID greater then the maxVersion.
     *
     * @param $changeLogTable
     * @param $idColumn
     * @param $maxVersion
     *
     * @return mixed - array of ID's
     */
    private function findProductIdsPending($changeLogTable, $idColumn, $maxVersion)
    {
        $connection = $this->getConnection();
        $joinCond = $this->checkTablePrefix('catalog_product_entity');
        $select = $connection->select()
            ->distinct()
            ->from($changeLogTable, $idColumn)
            ->join([$this->checkTablePrefix('catalog_product_entity') => $this->checkTablePrefix('catalog_product_entity')], "$changeLogTable.$idColumn = $joinCond.entity_id", [])
            ->where('version_id > ?', $maxVersion);

        return $connection->fetchCol($select);
    }

    /**
     * Find the latest version ID for a particular changeLog
     *
     * @param $changeLogTable
     * @return mixed
     */
    private function findLatestVersion($changeLogTable)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->checkTablePrefix('enterprise_mview_metadata'), 'version_id')
            ->where('changelog_name = ?', $changeLogTable);

        return $connection->fetchOne($select);
    }

    private function getConnection()
    {
        if (isset($this->dbConnection) === false) {
            /** @var Mage_Core_Model_Resource $coreResource */
            $coreResource = Mage::getSingleton('core/resource');
            $this->dbConnection = $coreResource->getConnection('core_read');
        }

        return $this->dbConnection;
    }


    /**
     * Call core/resource to check table name in case of table prefixing
     *
     * @param $table
     * @return mixed
     */
    private function checkTablePrefix($table)
    {
        return Mage::getSingleton('core/resource')->getTableName($table);
    }
}
