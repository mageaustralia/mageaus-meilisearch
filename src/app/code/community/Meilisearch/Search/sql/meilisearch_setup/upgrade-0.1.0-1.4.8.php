<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();

$orderItemTable = $installer->getTable('sales/order_item');
$idxName = $conn->getIndexName(
    $orderItemTable,
    ['product_id'],
    \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
);
if (!isset($conn->getIndexList($orderItemTable)[strtoupper($idxName)])) {
    $conn->addIndex($orderItemTable, $idxName, ['product_id']);
}

$reviewTable = $installer->getTable('review/review_aggregate');
$idxName = $conn->getIndexName(
    $reviewTable,
    ['store_id', 'entity_pk_value'],
    \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
);
if (!isset($conn->getIndexList($reviewTable)[strtoupper($idxName)])) {
    $conn->addIndex($reviewTable, $idxName, ['store_id', 'entity_pk_value']);
}

$installer->endSetup();
