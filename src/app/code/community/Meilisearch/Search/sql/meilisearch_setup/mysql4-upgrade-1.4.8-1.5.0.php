<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

/* Need a truncate since now everything is json_encoded and not serialized */
$installer->run("TRUNCATE TABLE `{$installer->getTable('meilisearch_search/queue')}`");
$installer->run("ALTER TABLE `{$installer->getTable('meilisearch_search/queue')}` ADD data_size INT(11);");

$index_prefix = Mage::getConfig()->getTablePrefix();
$installer->run('DELETE FROM `' . $index_prefix . "core_config_data` WHERE `path` LIKE '%meilisearch%'");

$installer->endSetup();
