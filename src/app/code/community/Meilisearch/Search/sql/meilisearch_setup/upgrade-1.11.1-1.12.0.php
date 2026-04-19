<?php

declare(strict_types=1);

// Same schema as 1.7.1 → 1.11.1 — kept to cover installs that jumped past
// that version without running the earlier upgrade.

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn       = $installer->getConnection();
$queueTable = $installer->getTable('meilisearch_search/queue');
$logTable   = $queueTable . '_log';

if (!$conn->tableColumnExists($queueTable, 'created')) {
    $conn->addColumn($queueTable, 'created', [
        'type'     => \Maho\Db\Ddl\Table::TYPE_DATETIME,
        'after'    => 'job_id',
        'nullable' => true,
        'comment'  => 'Time of job creation',
    ]);
}

if (!$conn->isTableExists($logTable)) {
    $ddl = $conn->newTable($logTable)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'ID')
        ->addColumn('started', \Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Started')
        ->addColumn('duration', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => false,
        ], 'Duration (seconds)')
        ->addColumn('processed_jobs', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable' => false,
        ], 'Processed Jobs')
        ->addColumn('with_empty_queue', \Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'nullable' => false,
        ], 'Ran With Empty Queue')
        ->setComment('Meilisearch Search Queue Log');

    $conn->createTable($ddl);
}

$installer->endSetup();
