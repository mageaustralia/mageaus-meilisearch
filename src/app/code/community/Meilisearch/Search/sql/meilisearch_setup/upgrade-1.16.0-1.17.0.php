<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Sales_Model_Resource_Setup('core_setup');

foreach (['quote_item', 'order_item'] as $entity) {
    $setup->addAttribute($entity, 'meilisearch_query_param', [
        'type'    => \Maho\Db\Ddl\Table::TYPE_TEXT,
        'grid'    => false,
        'comment' => 'Meilisearch Conversion Query Parameters',
    ]);
}

$installer->endSetup();
