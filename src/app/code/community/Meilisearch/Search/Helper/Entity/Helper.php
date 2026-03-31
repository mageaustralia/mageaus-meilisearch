<?php

abstract class Meilisearch_Search_Helper_Entity_Helper extends Mage_Core_Helper_Abstract
{
    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Meilisearch_Search_Helper_Logger */
    protected $logger;

    /** @var Meilisearch_Search_Helper_Meilisearchhelper */
    protected $meilisearch_helper;

    protected static $_activeCategories;
    protected static $_categoryNames;

    /** @var array */
    private $nonCastableAttributes = ['sku', 'name', 'description'];

    abstract protected function getIndexNameSuffix();

    public function __construct()
    {
        $this->config = Mage::helper('meilisearch_search/config');
        $this->meilisearch_helper = Mage::helper('meilisearch_search/meilisearchhelper');
        $this->logger = Mage::helper('meilisearch_search/logger');

        // Merge non castable attributes set in config
        $this->nonCastableAttributes = array_merge(
            $this->nonCastableAttributes,
            $this->config->getNonCastableAttributes(),
        );
    }

    public function getBaseIndexName($storeId = null)
    {
        return (string) $this->config->getIndexPrefix($storeId) . Mage::app()->getStore($storeId)->getCode();
    }

    public function getIndexName($storeId = null, $getTmpIndexName = false)
    {
        $indexName = (string) $this->getBaseIndexName($storeId) . $this->getIndexNameSuffix();

        if ($getTmpIndexName === true) {
            $indexName .= '_tmp';
        }

        return $indexName;
    }

    protected function try_cast($value)
    {
        if (is_numeric($value) && (float) $value == (float) ((int) $value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    protected function castProductObject(&$productData)
    {
        foreach ($productData as $key => &$data) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }

            $data = $this->try_cast($data);

            if (is_array($data) === false) {
                $data = explode('|', (string) $data);

                if (count($data) == 1) {
                    $data = $data[0];
                    $data = $this->try_cast($data);
                } else {
                    foreach ($data as &$element) {
                        $element = $this->try_cast($element);
                    }
                }
            }
        }
    }

    protected function strip($s, $completeRemoveTags = [])
    {
        if (!empty($completeRemoveTags)) {
            $dom = new DOMDocument();
            if (@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $s)) {
                $toRemove = [];
                foreach ($completeRemoveTags as $tag) {
                    $removeTags = $dom->getElementsByTagName($tag);

                    foreach ($removeTags as $item) {
                        $toRemove[] = $item;
                    }
                }

                foreach ($toRemove as $item) {
                    $item->parentNode->removeChild($item);
                }

                $s = $dom->saveHTML();
            }
        }

        $s = trim((string) preg_replace('/\s+/', ' ', (string) $s));
        $s = preg_replace('/&nbsp;/', ' ', $s);
        $s = preg_replace('!\s+!', ' ', (string) $s);
        $s = preg_replace('/\{\{[^}]+\}\}/', ' ', (string) $s);
        $s = strip_tags((string) $s);
        $s = trim($s);

        return $s;
    }

    public function isCategoryActive($categoryId, $storeId = null)
    {
        $storeId = (int) $storeId;
        $categoryId = (int) $categoryId;

        if ($path = $this->getCategoryPath($categoryId, $storeId)) {
            // Check whether the specified category is active

            $isActive = true; // Check whether all parent categories for the current category are active
            $parentCategoryIds = explode('/', (string) $path);

            if (count($parentCategoryIds) <= 2) {
                // Exclude root category

                return false;
            }

            array_shift($parentCategoryIds); // Remove root category

            array_pop($parentCategoryIds); // Remove current category as it is already verified

            $parentCategoryIds = array_reverse($parentCategoryIds); // Start from the first parent

            foreach ($parentCategoryIds as $parentCategoryId) {
                if (!($parentCategoryPath = $this->getCategoryPath($parentCategoryId, $storeId))) {
                    $isActive = false;
                    break;
                }
            }

            if ($isActive) {
                return true;
            }
        }

        return false;
    }

    public function getCategoryPath($categoryId, $storeId = null)
    {
        $categories = $this->getCategories();
        $storeId = (int) $storeId;
        $categoryId = (int) $categoryId;
        $path = null;
        $key = $storeId . '-' . $categoryId;

        if (isset($categories[$key])) {
            $path = ($categories[$key]['value'] == 1) ? (string) ($categories[$key]['path']) : null;
        } elseif ($storeId !== 0) {
            $key = '0-' . $categoryId;

            if (isset($categories[$key])) {
                $path = ($categories[$key]['value'] == 1) ? (string) ($categories[$key]['path']) : null;
            }
        }

        return $path;
    }

    public function getCategories()
    {
        if (is_null(self::$_activeCategories)) {
            self::$_activeCategories = [];

            /** @var Mage_Catalog_Model_Resource_Category $resource */
            $resource = Mage::getResourceModel('catalog/category');

            if ($attribute = $resource->getAttribute('is_active')) {
                /** @var Mage_Core_Model_Resource $coreResource */
                $coreResource = Mage::getSingleton('core/resource');
                $connection = $coreResource->getConnection('core_read');

                $select = $connection->select()->from(['backend' => $attribute->getBackendTable()], [
                    'key' => new Maho\Db\Expr("CONCAT(backend.store_id, '-', backend.entity_id)"),
                    'category.path',
                    'backend.value',
                ])->join(
                    ['category' => $resource->getTable('catalog/category')],
                    'backend.entity_id = category.entity_id',
                    [],
                )
                                     ->where('backend.entity_type_id = ?', $attribute->getEntityTypeId())
                                     ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                                     ->order('backend.store_id')->order('backend.entity_id');

                self::$_activeCategories = $connection->fetchAssoc($select);
            }
        }

        return self::$_activeCategories;
    }

    public function getCategoryName($categoryId, $storeId = null)
    {
        if ($categoryId instanceof Mage_Catalog_Model_Category) {
            $categoryId = $categoryId->getId();
        }

        if ($storeId instanceof Mage_Core_Model_Store) {
            $storeId = $storeId->getId();
        }

        $categoryId = (int) $categoryId;
        $storeId = (int) $storeId;

        if (is_null(self::$_categoryNames)) {
            self::$_categoryNames = [];

            $resource = Mage::getResourceModel('catalog/category');
            /** @var $resource Mage_Catalog_Model_Resource_Category */
            if ($attribute = $resource->getAttribute('name')) {
                /** @var Mage_Core_Model_Resource $coreResource */
                $coreResource = Mage::getSingleton('core/resource');
                $connection = $coreResource->getConnection('core_read');

                $select = $connection->select()->from(
                    ['backend' => $attribute->getBackendTable()],
                    [new Maho\Db\Expr("CONCAT(backend.store_id, '-', backend.entity_id)"), 'backend.value'],
                )
                                     ->join(
                                         ['category' => $resource->getTable('catalog/category')],
                                         'backend.entity_id = category.entity_id',
                                         [],
                                     )
                                     ->where('backend.entity_type_id = ?', $attribute->getEntityTypeId())
                                     ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                                     ->where('category.level > ?', 1);

                self::$_categoryNames = $connection->fetchPairs($select);
            }
        }

        $categoryName = null;

        $key = $storeId . '-' . $categoryId;

        if (isset(self::$_categoryNames[$key])) {
            // Check whether the category name is present for the specified store

            $categoryName = (string) (self::$_categoryNames[$key]);
        } elseif ($storeId != 0) {
            // Check whether the category name is present for the default store

            $key = '0-' . $categoryId;

            if (isset(self::$_categoryNames[$key])) {
                $categoryName = (string) (self::$_categoryNames[$key]);
            }
        }

        return $categoryName;
    }

    public static function getStores($store_id)
    {
        /** @var Meilisearch_Search_Helper_Config $config */
        $config = Mage::helper('meilisearch_search/config');
        $store_ids = [];

        if ($store_id == null) {
            /** @var Mage_Core_Model_Store $store */
            foreach (Mage::app()->getStores() as $store) {
                if ($config->isEnabledBackend($store->getId()) === false) {
                    continue;
                }

                if ($store->getIsActive()) {
                    $store_ids[] = $store->getId();
                }
            }
        } elseif (is_array($store_id)) {
            return $store_id;
        } else {
            $store_ids = [$store_id];
        }

        return $store_ids;
    }
}
