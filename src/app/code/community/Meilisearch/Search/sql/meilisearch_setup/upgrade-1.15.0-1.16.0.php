<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$conn  = $installer->getConnection();
$table = $installer->getTable('meilisearch_search/queue');

if (!$conn->tableColumnExists($table, 'locked_at')) {
    $conn->addColumn($table, 'locked_at', [
        'type'     => \Maho\Db\Ddl\Table::TYPE_DATETIME,
        'after'    => 'job_id',
        'nullable' => true,
        'comment'  => 'Time the job was locked for processing',
    ]);
}

$installer->endSetup();
