<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn  = $installer->getConnection();
$table = $installer->getTable('meilisearch_search/queue');

// Widen `data` to a long text column (json payloads outgrew the varchar 5000).
// TYPE_TEXT with a large size maps to LONGTEXT on MySQL, TEXT on Postgres, TEXT on SQLite.
$conn->modifyColumn($table, 'data', [
    'type'     => \Maho\Db\Ddl\Table::TYPE_TEXT,
    'length'   => '16M',
    'nullable' => false,
    'comment'  => 'Data',
]);

$installer->endSetup();
