<?php

require_once dirname(getenv('PWD')) . '../../../../../Mage.php';

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

@unlink(Mage::getBaseDir('var') . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'meilisearch_dump.log');

/** @var Meilisearch_Search_Helper_Config $config */
$config = Mage::helper('meilisearch_search/config');
$configReflection = new ReflectionClass($config::class);

$allMethods = $configReflection->getMethods(ReflectionMethod::IS_PUBLIC);

$configMethods = [];
foreach ($allMethods as $method) {
    if ($method->getDeclaringClass()->getName() == $config::class) {
        $parameters = $method->getParameters();
        $firstParamter = reset($parameters);
        if ($method->getNumberOfParameters() === 1 && $firstParamter->getName() === 'storeId') {
            $configMethods[] = $method->getName();
        }
    }
}

/** @var Mage_Core_Model_Resource $coreResource */
$coreResource = Mage::getSingleton('core/resource');
$db = $coreResource->getConnection('core_read');

$configTableName = $coreResource->getTableName('core/config_data');

$configRows = $db->query('SELECT path FROM ' . $configTableName . ' WHERE path LIKE "meilisearch%"')->fetchAll();

/** @var Mage_Core_Model_Store $store */
foreach (Mage::app()->getStores() as $store) {
    $storeId = $store->getId();

    meilisearch_dump_log('-- Dump config for store ID ' . $storeId . ' --');

    meilisearch_dump_log('-- Computed values --');
    foreach ($configMethods as $configMethod) {
        $result = $config->{$configMethod}($storeId);
        meilisearch_dump_log('$config->' . $configMethod . '(' . $storeId . ') === ' . var_export($result, true));
    }

    meilisearch_dump_log('-- Raw values --');
    foreach ($configRows as $row) {
        meilisearch_dump_log($row['path'] . ' === ' . var_export(Mage::getStoreConfig($row['path'], $storeId), true));
    }
}

echo "Dump file was successfully created.\n";

function meilisearch_dump_log($message)
{
    Mage::log($message, null, 'meilisearch_dump.log', true);
}
