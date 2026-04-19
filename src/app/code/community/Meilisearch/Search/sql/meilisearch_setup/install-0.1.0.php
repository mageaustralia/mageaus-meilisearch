<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn  = $installer->getConnection();
$table = $installer->getTable('meilisearch_search/queue');

if (!$conn->isTableExists($table)) {
    $ddl = $conn->newTable($table)
        ->addColumn('job_id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Job ID')
        ->addColumn('pid', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => true,
        ], 'PID')
        ->addColumn('class', \Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
            'nullable' => false,
        ], 'Class')
        ->addColumn('method', \Maho\Db\Ddl\Table::TYPE_TEXT, 50, [
            'nullable' => false,
        ], 'Method')
        ->addColumn('data', \Maho\Db\Ddl\Table::TYPE_TEXT, 5000, [
            'nullable' => false,
        ], 'Data')
        ->addColumn('max_retries', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => false,
            'default'  => 3,
        ], 'Max Retries')
        ->addColumn('retries', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => false,
            'default'  => 0,
        ], 'Retries')
        ->addColumn('error_log', \Maho\Db\Ddl\Table::TYPE_TEXT, null, [
            'nullable' => false,
            'default'  => '',
        ], 'Error Log')
        ->setComment('Meilisearch Search Queue');

    $conn->createTable($ddl);
}

$installer->endSetup();
