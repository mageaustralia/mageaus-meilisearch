<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn  = $installer->getConnection();
$table = $installer->getTable('meilisearch_search/queue_archive');

if (!$conn->isTableExists($table)) {
    $ddl = $conn->newTable($table)
        ->addColumn('pid', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => true,
        ], 'PID')
        ->addColumn('class', \Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
            'nullable' => false,
        ], 'Class')
        ->addColumn('method', \Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
            'nullable' => false,
        ], 'Method')
        ->addColumn('data', \Maho\Db\Ddl\Table::TYPE_TEXT, null, [
            'nullable' => false,
        ], 'Data')
        ->addColumn('error_log', \Maho\Db\Ddl\Table::TYPE_TEXT, null, [
            'nullable' => false,
        ], 'Error Log')
        ->addColumn('data_size', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => true,
        ], 'Data Size')
        ->addColumn('created_at', \Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Created At')
        ->setComment('Meilisearch Search Queue Archive');

    $conn->createTable($ddl);
}

$installer->endSetup();
