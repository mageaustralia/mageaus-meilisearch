<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn       = $installer->getConnection();
$queueTable = $installer->getTable('meilisearch_search/queue');
$configTable = $installer->getTable('core/config_data');

// Truncate queue: payload format changed from serialize to json_encode.
$conn->truncateTable($queueTable);

// Add data_size column if missing.
if (!$conn->tableColumnExists($queueTable, 'data_size')) {
    $conn->addColumn($queueTable, 'data_size', [
        'type'     => \Maho\Db\Ddl\Table::TYPE_INTEGER,
        'nullable' => true,
        'comment'  => 'Data Size',
    ]);
}

// Remove any legacy meilisearch config entries.
$conn->delete($configTable, ['path LIKE ?' => '%meilisearch%']);

$installer->endSetup();
