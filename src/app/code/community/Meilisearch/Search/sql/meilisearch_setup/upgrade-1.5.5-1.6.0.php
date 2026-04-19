<?php

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// "removeProducts" was replaced by "rebuildProductIndex" in the re-indexing
// refactor — clear the queue so stale jobs don't fail on dispatch.
$installer->getConnection()->delete($installer->getTable('meilisearch_search/queue'));

$installer->endSetup();
