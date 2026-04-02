<?php

use MeilisearchSearch\MeilisearchException;

class Meilisearch_Search_Helper_Entity_Producthelper extends Meilisearch_Search_Helper_Entity_Helper
{
    private $compositeTypes;

    protected static $_productAttributes;
    protected static $_currencies;

    protected static $_predefinedProductAttributes = [
        'name',
        'url_key',
        'description',
        'image',
        'small_image',
        'thumbnail',
        'msrp_enabled', // NEEDED to handle msrp behavior
        'tax_class_id', // Needed for tax calculation
        'price_type', // Needed for bundle prices
    ];

    private $excludedAttrsFromBundledProducts = [
        'news_from_date',
        'news_to_date',
        'special_price',
        'special_from_date',
        'special_to_date',
    ];

    private $noAttributes = [];

    protected function getIndexNameSuffix()
    {
        return '_products';
    }

    public function getAllAttributes($add_empty_row = false)
    {
        if (is_null(self::$_productAttributes)) {
            self::$_productAttributes = [];

            /** @var $config Mage_Eav_Model_Config */
            $config = Mage::getSingleton('eav/config');

            $allAttributes = $config->getEntityAttributeCodes('catalog_product');

            $productAttributes = array_merge([
                'name',
                'path',
                'categories',
                'categories.level0',
                'categories.level1',
                'categories.level2',
                'categories.level3',
                'categories.level4',
                'categories_without_path',
                'main_categories',
                'main_categories.level0',
                'main_categories.level1',
                'main_categories.level2',
                'main_categories.level3',
                'main_categories.level4',
                'description',
                'ordered_qty',
                'total_ordered',
                'stock_qty',
                'rating_summary',
                'media_gallery',
                'in_stock',
            ], $allAttributes);

            $excludedAttributes = $this->getExcludedAttributes();

            $productAttributes = array_diff($productAttributes, $excludedAttributes);

            foreach ($productAttributes as $attributeCode) {
                self::$_productAttributes[$attributeCode] = $config->getAttribute('catalog_product', $attributeCode)
                                                                   ->getFrontendLabel();
            }
        }

        $attributes = self::$_productAttributes;

        if ($add_empty_row === true) {
            $attributes[''] = '';
        }

        uksort($attributes, fn($a, $b) => strcmp((string) $a, (string) $b));

        return $attributes;
    }

    protected function getExcludedAttributes()
    {
        return [
            'all_children',
            'available_sort_by',
            'children',
            'children_count',
            'custom_apply_to_products',
            'custom_design',
            'custom_design_from',
            'custom_design_to',
            'custom_layout_update',
            'custom_use_parent_settings',
            'default_sort_by',
            'display_mode',
            'filter_price_range',
            'global_position',
            'image',
            'include_in_menu',
            'is_active',
            'is_always_include_in_menu',
            'is_anchor',
            'landing_page',
            'level',
            'lower_cms_block',
            'page_layout',
            'path_in_store',
            'position',
            'small_image',
            'thumbnail',
            'url_key',
            'url_path',
            'visible_in_menu',
        ];
    }

    public function isAttributeEnabled($additionalAttributes, $attr_name)
    {
        foreach ($additionalAttributes as $attr) {
            $additionalAttribute = $attr['attribute'];

            $dotPosition = strpos((string) $attr['attribute'], '.');
            if ($dotPosition !== false) {
                $additionalAttribute = substr((string) $attr['attribute'], 0, $dotPosition);
            }

            if ($additionalAttribute === $attr_name) {
                return true;
            }
        }

        return false;
    }

    public function getProductCollectionQuery($storeId, $productIds = null, $only_visible = true, $withoutData = false)
    {
        /** @var $products Mage_Catalog_Model_Resource_Product_Collection */
        $products = Mage::getResourceModel('catalog/product_collection');

        $products = $products->setStoreId($storeId);
        // Store filter: ensures only products assigned to this store's website are indexed

        $products = $products->addStoreFilter($storeId);
        $products = $products->distinct(true);

        if ($productIds && count($productIds) > 0) {
            $products = $products->addAttributeToFilter('entity_id', ['in' => $productIds]);
        }

        if ($only_visible) {
            $visibilityAttributeValues = $this->getVisibilityAttributeValues($storeId);

            $products = $products->addAttributeToFilter('visibility', ['in' => $visibilityAttributeValues]);
            $products = $products->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        }

        if ($withoutData === false) {
            if (false === $this->config->getShowOutOfStock($storeId) && $only_visible == true) {
                /** @var Mage_CatalogInventory_Model_Stock $catalogInventory_stock */
                $catalogInventory_stock = Mage::getSingleton('cataloginventory/stock');
                $catalogInventory_stock->addInStockFilterToCollection($products);
            }

            $products = $products
                ->addAttributeToSelect('special_from_date')
                ->addAttributeToSelect('special_to_date')
                ->addAttributeToSelect('visibility')
                ->addAttributeToSelect('status');

            $additionalAttr = $this->config->getProductAdditionalAttributes($storeId);

            /* Map instead of foreach because otherwise it adds quotes to the last attribute  **/
            $additionalAttr = array_map(fn($attr) => $attr['attribute'], $additionalAttr);

            $products = $products->addAttributeToSelect(array_values(array_merge(
                static::$_predefinedProductAttributes,
                $additionalAttr,
            )));

            $products->addPriceData();

            if ($only_visible === false) {
                $fromPart = $products->getSelect()->getPart(Varien_Db_Select::FROM);
                $fromPart['price_index']['joinType'] = 'left join';
                $products->getSelect()->setPart(Varien_Db_Select::FROM, $fromPart);
            }
        }

        Mage::dispatchEvent('meilisearch_rebuild_store_product_index_collection_load_before', ['store' => $storeId, 'collection' => $products]); // Only for backward compatibility
        Mage::dispatchEvent('meilisearch_after_products_collection_build', ['store' => $storeId, 'collection' => $products]);

        return $products;
    }

    public function setSettings($storeId, $saveToTmpIndicesToo = false)
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];
        $attributesForFaceting = [];

        // Always add configured barcode attribute and child barcodes as searchable (for POS barcode scanning)
        $barcodeAttributeCode = Mage::helper('core')->isModuleEnabled('Maho_Pos') ? Mage::helper('maho_pos')->getBarcodeAttributeCode() : 'gtin';
        $searchableAttributes[] = $barcodeAttributeCode;
        $searchableAttributes[] = 'child_' . $barcodeAttributeCode;

        foreach ($this->config->getProductAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['searchable'] == '1') {
                if ($attribute['order'] == 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] != '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }

            // Removed automatic addition of categories_without_path when categories is searchable
            // This was causing redundant indexing and affecting search ranking
            // if ($attribute['attribute'] == 'categories' && $attribute['searchable'] == '1') {
            //     $searchableAttributes[] = $attribute['order'] == 'ordered' ? 'categories_without_path' : 'unordered(categories_without_path)';
            // }
        }

        $customRankings = $this->config->getProductCustomRanking($storeId);

        $customRankingAttributes = [];

        $facets = $this->config->getFacets($storeId);

        /** @var Mage_Directory_Model_Currency $directoryCurrency */
        $directoryCurrency = Mage::getModel('directory/currency');
        $currencies = $directoryCurrency->getConfigAllowCurrencies();

        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->config->isCustomerGroupsEnabled($storeId)) {
                        /** @var Mage_Customer_Model_Group $group */
                        foreach ($groups = Mage::getModel('customer/group')->getCollection() as $group) {
                            $group_id = (int) $group->getData('customer_group_id');

                            $attributesForFaceting[] = 'price.' . $currency_code . '.group_' . $group_id;
                        }
                    }

                    $attributesForFaceting[] = $facet['attribute'];
                }
            } else {
                $attribute = $facet['attribute'];
                if (array_key_exists('searchable', $facet) && $facet['searchable'] === '1') {
                    $attribute = 'searchable(' . $attribute . ')';
                }

                $attributesForFaceting[] = $attribute;
            }
        }

        foreach ($customRankings as $ranking) {
            $customRankingAttributes[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        if ($this->config->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        // Always add category_ids and categories_without_path as filterable for POS and API filtering
        if (!in_array('category_ids', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'category_ids';
        }
        if (!in_array('categories_without_path', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories_without_path';
        }
        // Add sort_price as filterable for price range filtering
        if (!in_array('sort_price', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'sort_price';
        }

        // Build displayed attributes list (all attributes except those marked as non-retrievable)
        $allAttributes = $this->getAllAttributeNames($storeId);
        $displayedAttributes = array_diff($allAttributes, $unretrievableAttributes);

        // Get ranking rules from configuration or use defaults
        $rankingRules = $this->config->getRankingRules($storeId);

        // Add custom ranking after the configured rules
        if (!empty($customRankingAttributes)) {
            foreach ($customRankingAttributes as $customRanking) {
                $rankingRules[] = $customRanking;
            }
        }

        // Build sortable attributes including category position fields
        $sortableAttributes = ['sort_price', 'name', 'created_at', 'updated_at'];

        // Add category position fields for position-based sorting
        // Format: cat_position_{categoryId}
        $categoryPositionFields = $this->getAllCategoryPositionFields($storeId);
        $sortableAttributes = array_merge($sortableAttributes, $categoryPositionFields);

        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'rankingRules'            => $rankingRules,
            'displayedAttributes'     => array_values($displayedAttributes),
            'filterableAttributes'    => $attributesForFaceting,
            'sortableAttributes'      => array_values(array_unique($sortableAttributes)),
        ];

        // Additional index settings from event observer
        $transport = new Varien_Object($indexSettings);
        Mage::dispatchEvent('meilisearch_index_settings_prepare', ['store_id' => $storeId, 'index_settings' => $transport]); // Only for backward compatibility
        Mage::dispatchEvent('meilisearch_products_index_before_set_settings', ['store_id' => $storeId, 'index_settings' => $transport]);
        $indexSettings = $transport->getData();

        $indexName = $this->getIndexName($storeId);

        $mergeSettings = $this->meilisearch_helper->mergeSettings($indexName, $indexSettings);

        $this->meilisearch_helper->setSettings($indexName, $mergeSettings);
        if ($saveToTmpIndicesToo === true) {
            $this->meilisearch_helper->setSettings($indexName . '_tmp', $mergeSettings);
        }

        $this->setFacetsQueryRules($indexName);
        if ($saveToTmpIndicesToo === true) {
            $this->setFacetsQueryRules($indexName . '_tmp');
        }

        /*
         * Handle replicas
         */
        $sorting_indices = $this->config->getSortingIndices($storeId);

        $replicas = [];

        if (count($sorting_indices) > 0) {
            foreach ($sorting_indices as $values) {
                if ($this->config->isCustomerGroupsEnabled($storeId) && $values['attribute'] === 'price') {
                    foreach ($groups = Mage::getModel('customer/group')->getCollection() as $group) {
                        $group_id = (int) $group->getData('customer_group_id');

                        $suffix_index_name = 'group_' . $group_id;

                        $replicas[] = $this->getIndexName($storeId) . '_' . $values['attribute'] . '_' . $suffix_index_name . '_' . $values['sort'];
                    }
                } else {
                    if ($values['attribute'] === 'price') {
                        $replicas[] = $this->getIndexName($storeId) . '_' . $values['attribute'] . '_default_' . $values['sort'];
                    } else {
                        $replicas[] = $this->getIndexName($storeId) . '_' . $values['attribute'] . '_' . $values['sort'];
                    }
                }
            }

            // Merge current replicas with sorting replicas to not delete A/B testing replica indices
            try {
                $currentSettings = $this->meilisearch_helper->getIndexSettings($indexName);
                if (array_key_exists('replicas', $currentSettings)) {
                    $replicas = array_values(array_unique(array_merge($replicas, $currentSettings['replicas'])));
                }
            } catch (\Exception $e) {
                if ($e->getMessage() !== 'Index does not exist') {
                    throw $e;
                }
            }

            $this->meilisearch_helper->setSettings($this->getIndexName($storeId), ['replicas' => $replicas]);
            // $setReplicasTaskId = $this->meilisearch_helper->getLastTaskId();

            /** @var Mage_Core_Model_Store $store */
            $store = Mage::getModel('core/store')->load($storeId);
            $baseCurrencyCode = $store->getBaseCurrencyCode();

            foreach ($sorting_indices as $values) {
                if ($this->config->isCustomerGroupsEnabled($storeId) && $values['attribute'] === 'price') {
                    foreach ($groups = Mage::getModel('customer/group')->getCollection() as $group) {
                        $group_id = (int) $group->getData('customer_group_id');

                        $suffix_index_name = 'group_' . $group_id;

                        $sort_attribute = $values['attribute'] === 'price' ? $values['attribute'] . '.' . $baseCurrencyCode . '.' . $suffix_index_name : $values['attribute'];

                        $mergeSettings['ranking'] = [
                            $values['sort'] . '(' . $sort_attribute . ')',
                            'typo',
                            'geo',
                            'words',
                            'filters',
                            'proximity',
                            'attribute',
                            'exact',
                            'custom',
                        ];

                        $this->meilisearch_helper->setSettings(
                            $this->getIndexName($storeId) . '_' . $values['attribute'] . '_' . $suffix_index_name . '_' . $values['sort'],
                            $mergeSettings,
                        );
                    }
                } else {
                    $sort_attribute = $values['attribute'] === 'price' ? $values['attribute'] . '.' . $baseCurrencyCode . '.' . 'default' : $values['attribute'];

                    $mergeSettings['ranking'] = [
                        $values['sort'] . '(' . $sort_attribute . ')',
                        'typo',
                        'geo',
                        'words',
                        'filters',
                        'proximity',
                        'attribute',
                        'exact',
                        'custom',
                    ];

                    if ($values['attribute'] === 'price') {
                        $this->meilisearch_helper->setSettings(
                            $this->getIndexName($storeId) . '_' . $values['attribute'] . '_default_' . $values['sort'],
                            $mergeSettings,
                        );
                    } else {
                        $this->meilisearch_helper->setSettings(
                            $this->getIndexName($storeId) . '_' . $values['attribute'] . '_' . $values['sort'],
                            $mergeSettings,
                        );
                    }
                }
            }
        } else {
            $this->meilisearch_helper->setSettings($this->getIndexName($storeId), ['replicas' => $replicas]);
            // $setReplicasTaskId = $this->meilisearch_helper->getLastTaskId();
        }

        // Commented out as it doesn't delete anything now because of merging replica indices earlier
        // $this->deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId);

        if ($this->config->isEnabledSynonyms($storeId) === true) {
            if ($synonymsFile = $this->config->getSynonymsFile($storeId)) {
                $synonymsToSet = json_decode(file_get_contents($synonymsFile));
            } else {
                $synonymsToSet = [];

                $synonyms = $this->config->getSynonyms($storeId);
                foreach ($synonyms as $objectID => $synonym) {
                    if (!trim((string) $synonym['synonyms'])) {
                        continue;
                    }

                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'synonym',
                        'synonyms' => $this->explodeSynomyms($synonym['synonyms']),
                    ];
                }

                $onewaySynonyms = $this->config->getOnewaySynonyms($storeId);
                foreach ($onewaySynonyms as $objectID => $onewaySynonym) {
                    if (!trim((string) $onewaySynonym['input']) || !trim((string) $onewaySynonym['synonyms'])) {
                        continue;
                    }

                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'oneWaySynonym',
                        'input' => $onewaySynonym['input'],
                        'synonyms' => $this->explodeSynomyms($onewaySynonym['synonyms']),
                    ];
                }
            }

            $this->meilisearch_helper->setSynonyms($this->getIndexName($storeId, $saveToTmpIndicesToo), $synonymsToSet);
        } elseif ($saveToTmpIndicesToo === true) {
            $this->meilisearch_helper->copySynonyms($this->getIndexName($storeId), $this->getIndexName($storeId, $saveToTmpIndicesToo));
        }

        if ($saveToTmpIndicesToo === true) {
            $indexName = $this->getIndexName($storeId);
            $indexNameTmp = $this->getIndexName($storeId, $saveToTmpIndicesToo);

        }
    }

    protected function getFields($store)
    {
        /** @var Mage_Tax_Helper_Data $tax_helper */
        $tax_helper = Mage::helper('tax');

        if ($tax_helper->getPriceDisplayType($store) == Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX) {
            return ['price' => false];
        }

        if ($tax_helper->getPriceDisplayType($store) == Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX) {
            return ['price' => true];
        }

        return ['price' => false, 'price_with_tax' => true];
    }

    protected function formatPrice($price, $includeContainer, $currency_code)
    {
        /** @var Mage_Directory_Model_Currency $directoryCurrency */
        $directoryCurrency = Mage::getModel('directory/currency');

        if (!isset(static::$_currencies[$currency_code])) {
            static::$_currencies[$currency_code] = $directoryCurrency->load($currency_code);
        }

        /** @var Mage_Directory_Model_Currency $currency */
        $currency = static::$_currencies[$currency_code];

        if ($currency) {
            return $currency->format($price, [], $includeContainer);
        }

        return $price;
    }

    protected function handlePrice(Mage_Catalog_Model_Product &$product, $sub_products, &$customData)
    {
        $fields = $this->getFields($product->getStore());
        $customer_groups_enabled = $this->config->isCustomerGroupsEnabled($product->getStoreId());
        $store = $product->getStore();
        $type = $this->config->getMappedProductType($product->getTypeId());

        /** @var Mage_Directory_Model_Currency $directoryCurrency */
        $directoryCurrency = Mage::getModel('directory/currency');
        $currencies = $directoryCurrency->getConfigAllowCurrencies();

        if (Mage::helper('core')->isModuleEnabled('Mage_Weee') &&
            Mage::helper('weee')->getPriceDisplayType($product->getStore()) == 0) {
            $weeeTaxAmount = Mage::helper('weee')->getAmountForDisplay($product);
        } else {
            $weeeTaxAmount = 0;
        }

        $baseCurrencyCode = $store->getBaseCurrencyCode();

        $groups = [];

        if ($customer_groups_enabled) {
            $groups = Mage::getModel('customer/group')->getCollection();
        }

        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');

        /** @var Mage_Directory_Helper_Data $directoryHelper */
        $directoryHelper = Mage::helper('directory');

        foreach ($fields as $field => $with_tax) {
            $customData[$field] = [];

            foreach ($currencies as $currency_code) {
                $customData[$field][$currency_code] = [];

                $price = (float) $taxHelper->getPrice($product, $product->getPrice(), $with_tax, null, null, null, $product->getStore(), null);
                $price = $directoryHelper->currencyConvert($price, $baseCurrencyCode, $currency_code);
                $price += $weeeTaxAmount;

                $customData[$field][$currency_code]['default'] = $price;
                $customData[$field][$currency_code]['default_formated'] = $this->formatPrice($price, false, $currency_code);

                $special_price = (float) $taxHelper->getPrice($product, $product->getFinalPrice(), $with_tax, null, null, null, $product->getStore(), null);
                $special_price = $directoryHelper->currencyConvert($special_price, $baseCurrencyCode, $currency_code);
                $special_price += $weeeTaxAmount;

                if ($customer_groups_enabled) {
                    // If fetch special price for groups

                    foreach ($groups as $group) {
                        $group_id = (int) $group->getData('customer_group_id');
                        $product->setCustomerGroupId($group_id);

                        $discounted_price = $product->getPriceModel()->getFinalPrice(1, $product);
                        $discounted_price = $directoryHelper->currencyConvert($discounted_price, $baseCurrencyCode, $currency_code);
                        $discounted_price += $weeeTaxAmount;

                        if ($discounted_price !== false) {
                            $customData[$field][$currency_code]['group_' . $group_id] = (float) $taxHelper->getPrice(
                                $product,
                                $discounted_price,
                                $with_tax,
                                null,
                                null,
                                null,
                                $product->getStore(),
                                null,
                            );
                            $customData[$field][$currency_code]['group_' . $group_id] = $directoryHelper->currencyConvert(
                                $customData[$field][$currency_code]['group_' . $group_id],
                                $baseCurrencyCode,
                                $currency_code,
                            );
                            $customData[$field][$currency_code]['group_' . $group_id . '_formated'] = $store->formatPrice(
                                $customData[$field][$currency_code]['group_' . $group_id],
                                false,
                            );
                        } else {
                            $customData[$field][$currency_code]['group_' . $group_id] = $customData[$field][$currency_code]['default'];
                            $customData[$field][$currency_code]['group_' . $group_id . '_formated'] = $customData[$field][$currency_code]['default_formated'];
                        }
                    }

                    $product->setCustomerGroupId(null);
                }

                $customData[$field][$currency_code]['special_from_date'] = strtotime((string) $product->getSpecialFromDate());
                $customData[$field][$currency_code]['special_to_date'] = strtotime((string) $product->getSpecialToDate());

                if ($customer_groups_enabled) {
                    foreach ($groups as $group) {
                        $group_id = (int) $group->getData('customer_group_id');

                        if ($special_price && $special_price < $customData[$field][$currency_code]['group_' . $group_id]) {
                            $customData[$field][$currency_code]['group_' . $group_id . '_original_formated'] =
                                $customData[$field][$currency_code]['default_formated'];

                            $customData[$field][$currency_code]['group_' . $group_id] = $special_price;
                            $customData[$field][$currency_code]['group_' . $group_id . '_formated'] = $this->formatPrice(
                                $special_price,
                                false,
                                $currency_code,
                            );
                        }
                    }
                }

                if ($special_price && $special_price < $customData[$field][$currency_code]['default']) {
                    $customData[$field][$currency_code]['default_original_formated'] =
                        $customData[$field][$currency_code]['default_formated'];

                    $customData[$field][$currency_code]['default'] = $special_price;
                    $customData[$field][$currency_code]['default_formated'] = $this->formatPrice(
                        $special_price,
                        false,
                        $currency_code,
                    );
                }

                if ($type == 'grouped' || $type == 'bundle' || $type == 'configurable') {
                    $min = PHP_INT_MAX;
                    $max = 0;

                    if ($type == 'bundle') {
                        $_priceModel = $product->getPriceModel();

                        [$min, $max] = $_priceModel->getTotalPrices($product, null, $with_tax, true);
                        $min = (float) $min;
                        $max = (float) $max;
                    } else {
                        if (count($sub_products) > 0) {
                            foreach ($sub_products as $sub_product) {
                                if (!$sub_product->isSalable()) {
                                    continue;
                                }

                                $price = (float) $taxHelper->getPrice(
                                    $product,
                                    $sub_product->getFinalPrice(),
                                    $with_tax,
                                    null,
                                    null,
                                    null,
                                    $product->getStore(),
                                    null,
                                );

                                $min = min($min, $price);
                                $max = max($max, $price);
                            }
                        }

                        if ($min > $max) {
                            $min = $max;
                        } // avoid to have PHP_INT_MAX in case of no salable subproducts (Corner case of visibility and stock options)
                    }

                    if ($min != $max) {
                        $min = $directoryHelper->currencyConvert($min, $baseCurrencyCode, $currency_code);
                        $max = $directoryHelper->currencyConvert($max, $baseCurrencyCode, $currency_code);

                        $dashed_format = $this->formatPrice($min, false, $currency_code) . ' - ' . $this->formatPrice(
                            $max,
                            false,
                            $currency_code,
                        );

                        if (isset($customData[$field][$currency_code]['default_original_formated']) === false || $min <= $customData[$field][$currency_code]['default']) {
                            $customData[$field][$currency_code]['default_formated'] = $dashed_format;

                            //// Do not keep special price that is already taken into account in min max
                            unset($customData['price']['special_from_date']);
                            unset($customData['price']['special_to_date']);
                            unset($customData['price']['default_original_formated']);

                            $customData[$field][$currency_code]['default'] = 0; // will be reset just after
                        }

                        if ($customer_groups_enabled) {
                            foreach ($groups as $group) {
                                $group_id = (int) $group->getData('customer_group_id');

                                if ($min != $max && $min <= $customData[$field][$currency_code]['group_' . $group_id]) {
                                    $customData[$field][$currency_code]['group_' . $group_id] = 0;
                                } else {
                                    $customData[$field][$currency_code]['group_' . $group_id] = $customData[$field][$currency_code]['default'];
                                }

                                $customData[$field][$currency_code]['group_' . $group_id . '_formated'] = $dashed_format;
                            }
                        }
                    }

                    if ($customData[$field][$currency_code]['default'] == 0) {
                        $customData[$field][$currency_code]['default'] = $min;

                        if ($min === $max) {
                            $customData[$field][$currency_code]['default_formated'] = $this->formatPrice(
                                $min,
                                false,
                                $currency_code,
                            );
                        }
                    }

                    if ($customer_groups_enabled) {
                        foreach ($groups as $group) {
                            $group_id = (int) $group->getData('customer_group_id');

                            if ($customData[$field][$currency_code]['group_' . $group_id] == 0) {
                                $customData[$field][$currency_code]['group_' . $group_id] = $min;

                                if ($min === $max) {
                                    $customData[$field][$currency_code]['group_' . $group_id . '_formated'] = $customData[$field][$currency_code]['default_formated'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getValueOrValueText(Mage_Catalog_Model_Product $product, $name, Mage_Catalog_Model_Resource_Eav_Attribute $resource)
    {
        $value_text = $product->getAttributeText($name);
        if (!$value_text) {
            $value_text = $resource->getFrontend()->getValue($product);
        }

        return $value_text;
    }

    public function getObject(Mage_Catalog_Model_Product $product)
    {
        $type = $this->config->getMappedProductType($product->getTypeId());
        $this->logger->start('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($product->storeId));
        $this->logger->log('Product type (' . $product->getTypeId() . ', mapped to: ' . $type . ')');

        $defaultData = [];

        $transport = new Varien_Object($defaultData);
        Mage::dispatchEvent('meilisearch_product_index_before', ['product' => $product, 'custom_data' => $transport]);
        $defaultData = $transport->getData();

        $defaultData = is_array($defaultData) ? $defaultData : explode('|', (string) $defaultData);

        $visibility = (int) $product->getVisibility();

        /** @var Mage_Catalog_Model_Product_Visibility $catalogProductVisibility */
        $catalogProductVisibility = Mage::getSingleton('catalog/product_visibility');
        $visibleInCatalog = $catalogProductVisibility->getVisibleInCatalogIds();
        $visibleInSearch = $catalogProductVisibility->getVisibleInSearchIds();

        $customData = [
            'objectID'           => $product->getId(),
            'name'               => $product->getName(),
            'url'                => $product->getProductUrl(false),
            'visibility_search'  => (int) (in_array($visibility, $visibleInSearch)),
            'visibility_catalog' => (int) (in_array($visibility, $visibleInCatalog)),
        ];

        $additionalAttributes = $this->config->getProductAdditionalAttributes($product->getStoreId());
        $groups = null;

        if ($this->isAttributeEnabled($additionalAttributes, 'description')) {
            $customData['description'] = $product->getDescription();
        }

        $categories = [];
        $categories_with_path = [];

        $_categoryIds = $product->getCategoryIds();

        if (is_array($_categoryIds) && count($_categoryIds) > 0) {
            $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                                      ->distinct(true)
                                      ->addAttributeToSelect('name')
                                      ->addAttributeToFilter('entity_id', $_categoryIds)
                                      ->addFieldToFilter('level', ['gt' => 1])
                                      ->addIsActiveFilter();

            if ($this->config->showCatsNotIncludedInNavigation($product->getStoreId()) == false) {
                $categoryCollection->addAttributeToFilter('include_in_menu', '1');
            }

            $rootCat = Mage::app()->getStore($product->getStoreId())->getRootCategoryId();

            /** @var Mage_Catalog_Model_Category $category */
            foreach ($categoryCollection as $category) {
                // Check and skip all categories that is not
                // in the path of the current store.
                $path = $category->getPath();
                $path_parts = explode('/', $path);
                if (isset($path_parts[1]) && $path_parts[1] != $rootCat) {
                    continue;
                }

                $categoryName = $category->getName();

                if ($categoryName) {
                    $categories[] = $categoryName;
                }

                $category->getUrlInstance()->setStore($product->getStoreId());
                $path = [];

                foreach ($category->getPathIds() as $treeCategoryId) {
                    $name = $this->getCategoryName($treeCategoryId, $product->getStoreId());
                    if ($name) {
                        $path[] = $name;
                    }
                }

                $categories_with_path[] = $path;
            }
        }

        if ($this->config->indexWholeCategoryTree($product->getStoreId())) {
            foreach ($categories_with_path as $result) {
                for ($i = count($result) - 1; $i > 0; $i--) {
                    $categories_with_path[] = array_slice($result, 0, $i);
                }
            }
        }

        $categories_with_path = array_intersect_key(
            $categories_with_path,
            array_unique(array_map(serialize(...), $categories_with_path)),
        );

        $categories_hierarchical = [];
        $mainCategories = [];

        $level_name = 'level';

        /** @var array $category */
        foreach ($categories_with_path as $category) {
            $categoriesCount = count($category);

            for ($i = 0; $i < $categoriesCount; $i++) {
                if (isset($categories_hierarchical[$level_name . $i]) === false) {
                    $categories_hierarchical[$level_name . $i] = [];
                }

                $mainCategories[$level_name . $i][] = $category[$i];

                if ($this->config->indexWholeCategoryTree($product->getStoreId())) {
                    $categories_hierarchical[$level_name . $i][] = implode(' /// ', array_slice($category, 0, $i + 1));
                } else {
                    if ($i === ($categoriesCount - 1)) {
                        $categories_hierarchical[$level_name . $i][] = implode(' /// ', $category);
                    }
                }
            }
        }

        foreach ($categories_hierarchical as &$level) {
            $level = array_values(array_unique($level));
        }

        foreach ($mainCategories as &$level) {
            $level = array_values(array_unique($level));
        }

        foreach ($categories_with_path as &$category) {
            $category = implode(' /// ', $category);
        }

        $customData['categories'] = $categories_hierarchical;
        $customData['categories_without_path'] = $categories;
        $customData['category_ids'] = array_map(intval(...), $_categoryIds); // Store category IDs for direct filtering

        // Index category positions for position-based sorting
        // Each product has a position within each category (from catalog_category_product table)
        $categoryPositions = $this->getCategoryPositions($product->getId(), $_categoryIds);
        foreach ($categoryPositions as $catId => $position) {
            $customData['cat_position_' . $catId] = (int) $position;
        }

        if ($this->isAttributeEnabled($additionalAttributes, 'main_categories')) {
            $customData['main_categories'] = $mainCategories;
        }

        /** @var Meilisearch_Search_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('meilisearch_search/image');

        // Debug: Log image attribute values
        $this->logger->log('Product ' . $product->getId() . ' image attrs: image=' . var_export($product->getData('image'), true) . ', thumbnail=' . var_export($product->getData('thumbnail'), true));

        if (false === isset($defaultData['thumbnail_url'])) {
            /** @var Meilisearch_Search_Helper_Image $thumb */
            $thumb = $imageHelper->init($product, 'thumbnail')->resize(100, 100);

            try {
                $customData['thumbnail_url'] = (string) $thumb;
            } catch (\Exception $e) {
                $this->logger->log($e->getMessage());
                $this->logger->log($e->getTraceAsString());

                $placeholderUrl = Mage::getDesign()->getSkinUrl($thumb->getPlaceholder());

                $customData['thumbnail_url'] = $imageHelper->removeProtocol($placeholderUrl);
            }
        }

        // Small image for category listings (400x400 for good quality at ~300px display)
        if (false === isset($defaultData['small_image_url'])) {
            /** @var Meilisearch_Search_Helper_Image $smallImage */
            $smallImage = $imageHelper->init($product, 'small_image')->resize(400, 400);

            try {
                $customData['small_image_url'] = (string) $smallImage;
            } catch (\Exception $e) {
                $this->logger->log($e->getMessage());
                $this->logger->log($e->getTraceAsString());

                $placeholderUrl = Mage::getDesign()->getSkinUrl($smallImage->getPlaceholder());

                $customData['small_image_url'] = $imageHelper->removeProtocol($placeholderUrl);
            }
        }

        if (false === isset($defaultData['image_url'])) {
            /** @var Meilisearch_Search_Helper_Image $image */
            $image = $imageHelper->init($product, $this->config->getImageType())
                         ->resize($this->config->getImageWidth(), $this->config->getImageHeight());

            try {
                $customData['image_url'] = (string) $image;
            } catch (\Exception $e) {
                $this->logger->log($e->getMessage());
                $this->logger->log($e->getTraceAsString());

                $placeholderUrl = Mage::getDesign()->getSkinUrl($image->getPlaceholder());

                $customData['image_url'] = $imageHelper->removeProtocol($placeholderUrl);
            }

            if ($this->isAttributeEnabled($additionalAttributes, 'media_gallery')) {
                $product->load('media_gallery');

                $customData['media_gallery'] = [];

                foreach ($product->getMediaGalleryImages() as $image) {
                    $customData['media_gallery'][] = $imageHelper->removeProtocol($image->getUrl());
                }
            }
        }

        $sub_products = null;
        $ids = null;

        if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
            if ($type == 'bundle') {
                $ids = [];

                $selection = $product->getTypeInstance(true)->getSelectionsCollection(
                    $product->getTypeInstance(true)
                                                                                              ->getOptionsIds($product),
                    $product,
                );

                foreach ($selection as $option) {
                    $ids[] = $option->product_id;
                }
            }

            if ($type == 'configurable' || $type == 'grouped') {
                $ids = $product->getTypeInstance(true)->getChildrenIds($product->getId());
                $ids = call_user_func_array(array_merge(...), $ids);
            }

            if (count($ids)) {
                $collection = $this->getProductCollectionQuery($product->getStoreId(), $ids, false);
                $sub_products = $collection->load();
            } else {
                $sub_products = [];
            }
        }

        if (false === isset($defaultData['in_stock'])) {
            $stockItem = $product->getStockItem();

            $customData['in_stock'] = (int) $stockItem->getIsInStock();
        }

        // skip default calculation if we have provided these attributes via the observer in $defaultData
        if (false === isset($defaultData['ordered_qty']) && $this->isAttributeEnabled(
            $additionalAttributes,
            'ordered_qty',
        )
        ) {
            $customData['ordered_qty'] = (int) $product->getOrderedQty();
        }

        if (false === isset($defaultData['total_ordered']) && $this->isAttributeEnabled(
            $additionalAttributes,
            'total_ordered',
        )
        ) {
            $customData['total_ordered'] = (int) $product->getTotalOrdered();
        }

        if (false === isset($defaultData['stock_qty']) && $this->isAttributeEnabled(
            $additionalAttributes,
            'stock_qty',
        )
        ) {
            $customData['stock_qty'] = (int) $product->getStockQty();
        }

        if (Mage::helper('core')->isModuleEnabled('Mage_Review')) {
            if ($this->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
                $customData['rating_summary'] = (int) $product->getRatingSummary();
            }
        }

        $this->setNoAttributes($additionalAttributes);

        foreach ($additionalAttributes as $attribute) {
            $attribute_name = $attribute['attribute'];
            if (array_key_exists($attribute_name, $customData)) {
                continue;
            }

            $value = $product->getData($attribute_name);

            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute_resource */
            $attribute_resource = $product->getResource()->getAttribute($attribute_name);

            if ($attribute_resource) {
                $attribute_resource->setStoreId($product->getStoreId());

                $values = [];
                $subProductImages = [];

                /**
                 * if $value is missing or if the attribute is SKU,
                 * use values from child products.
                 */
                if (($value === null || 'sku' == $attribute_name) && ($type == 'configurable' || $type == 'grouped' || $type == 'bundle')) {
                    if ($value !== null) {
                        $values[] = $this->getValueOrValueText($product, $attribute_name, $attribute_resource);
                    }

                    $all_sub_products_out_of_stock = true;

                    if ($type !== 'bundle' || in_array($attribute_name, $this->excludedAttrsFromBundledProducts, true) === false) {
                        foreach ($sub_products as $sub_product) {
                            $isInStock = (int) $sub_product->getStockItem()->getIsInStock();

                            if ($isInStock == false && $this->config->indexOutOfStockOptions($product->getStoreId()) == false) {
                                continue;
                            }

                            $all_sub_products_out_of_stock = false;

                            $value = $sub_product->getData($attribute_name);

                            if ($value) {
                                $textValue = $this->getValueOrValueText($sub_product, $attribute_name, $attribute_resource);

                                $values[] = $textValue;

                                if (mb_strtolower((string) $attribute_name, 'utf-8') === 'color') {
                                    $image = $imageHelper->init($sub_product, $this->config->getImageType())
                                                         ->resize(
                                                             $this->config->getImageWidth(),
                                                             $this->config->getImageHeight(),
                                                         );

                                    try {
                                        $textValueInLower = mb_strtolower((string) $textValue, 'utf-8');
                                        $subProductImages[$textValueInLower] = (string) $image;
                                    } catch (\Exception $e) {
                                        $this->logger->log($e->getMessage());
                                        $this->logger->log($e->getTraceAsString());
                                    }
                                }
                            }
                        }
                    }

                    if (is_array($values) && count($values) > 0) {
                        $customData[$attribute_name] = array_values(array_unique($values, SORT_REGULAR));
                    }

                    if (empty($subProductImages) === false) {
                        $customData['images_data'] = $subProductImages;
                    }

                    // Set main product out of stock if all
                    // sub-products are out of stock.
                    if ($customData['in_stock'] && $all_sub_products_out_of_stock) {
                        $customData['in_stock'] = 0;
                    }
                } elseif (!is_array($value)) {
                    $value = $this->getValueOrValueText($product, $attribute_name, $attribute_resource);
                }

                if ($value && !isset($customData[$attribute_name])) {
                    $customData[$attribute_name] = $value;
                }
            }
        }

        $msrpEnabled = method_exists(Mage::helper('catalog'), 'canApplyMsrp') ? (bool) Mage::helper('catalog')
                                                                                          ->canApplyMsrp($product) : false;

        if (false === $msrpEnabled) {
            $this->handlePrice($product, $sub_products, $customData);

            // Add flat sort_price for Meilisearch sorting (nested price objects don't work)
            $baseCurrencyCode = Mage::app()->getStore($product->getStoreId())->getBaseCurrencyCode();
            if (isset($customData['price'][$baseCurrencyCode]['default'])) {
                $customData['sort_price'] = (float) $customData['price'][$baseCurrencyCode]['default'];
            } elseif ($product->getFinalPrice()) {
                $customData['sort_price'] = (float) $product->getFinalPrice();
            }
        } else {
            unset($customData['price']);
        }

        // Only for backward compatibility
        $transport = new Varien_Object($customData);
        Mage::dispatchEvent('meilisearch_subproducts_index', ['custom_data' => $transport, 'sub_products' => $sub_products, 'productObject' => $product]);
        $customData = $transport->getData();

        $customData = array_merge($customData, $defaultData);

        $customData['type_id'] = $type;

        // For POS: Add child barcodes to configurable products for barcode scanning
        if (($type == 'configurable' || $type == 'grouped') && $sub_products && count($sub_products) > 0) {
            $childBarcodes = [];

            // Get configured barcode attribute code
            $barcodeAttributeCode = Mage::helper('core')->isModuleEnabled('Maho_Pos') ? Mage::helper('maho_pos')->getBarcodeAttributeCode() : 'gtin';

            // Check if barcode attribute exists before trying to access it
            $barcodeAttribute = $product->getResource()->getAttribute($barcodeAttributeCode);

            if ($barcodeAttribute && $barcodeAttribute->getId()) {
                foreach ($sub_products as $subProduct) {
                    $barcode = $subProduct->getData($barcodeAttributeCode);
                    if ($barcode && !empty($barcode)) {
                        $childBarcodes[] = $barcode;
                    }
                }

                if (!empty($childBarcodes)) {
                    // Keep parent barcode in main field, add all child barcodes to array
                    $customData['child_' . $barcodeAttributeCode] = $childBarcodes;
                }
            }
        }

        $this->castProductObject($customData);

        $customData = $this->clearNoValues($customData);

        $transport = new Varien_Object($customData);
        Mage::dispatchEvent('meilisearch_after_create_product_object', ['product_data' => $transport, 'sub_products' => $sub_products, 'productObject' => $product]);
        $customData = $transport->getData();

        $this->logger->stop('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($product->storeId));

        return $customData;
    }

    /**
     * Get all possible attribute names that could be in the index
     *
     * @param int $storeId
     * @return array
     */
    protected function getAllAttributeNames($storeId)
    {
        // Start with default attributes that are always present
        $attributes = [
            'objectID', 'name', 'url', 'visibility_search', 'visibility_catalog',
            'categories', 'categories_without_path', 'category_ids', 'thumbnail_url', 'small_image_url', 'image_url',
            'in_stock', 'sku', 'price', 'sort_price', 'created_at', 'type_id',
        ];

        // Add conditional attributes only if they're enabled
        $conditionalAttributes = [
            'description' => 'description',
            'ordered_qty' => 'ordered_qty',
            'total_ordered' => 'total_ordered',
            'stock_qty' => 'stock_qty',
            'rating_summary' => 'rating_summary',
            'media_gallery' => 'media_gallery',
            'main_categories' => 'main_categories',
        ];

        foreach ($conditionalAttributes as $attr) {
            if ($this->isAttributeEnabled($this->config->getProductAdditionalAttributes($storeId), $attr)) {
                $attributes[] = $attr;
            }
        }

        // Add all additional attributes from configuration
        $additionalAttributes = $this->config->getProductAdditionalAttributes($storeId);
        foreach ($additionalAttributes as $attribute) {
            $attributeName = $attribute['attribute'];

            // Handle nested attributes (e.g., color.value)
            $dotPosition = strpos((string) $attributeName, '.');
            if ($dotPosition !== false) {
                $attributeName = substr((string) $attributeName, 0, $dotPosition);
            }

            if (!in_array($attributeName, $attributes)) {
                $attributes[] = $attributeName;
            }
        }

        // Add any custom attributes that might be added via events
        $attributes[] = 'path';
        $attributes[] = 'algoliaLastUpdateAtCET'; // Legacy compatibility

        // Price is already included as a general attribute
        // Individual currency prices are handled as sub-attributes

        return $attributes;
    }

    /**
     * Get all category position field names for sortable attributes
     *
     * @param int $storeId
     * @return array Field names like ['cat_position_4', 'cat_position_5', ...]
     */
    private function getAllCategoryPositionFields($storeId)
    {
        $fields = [];

        // Get all active categories for this store
        $rootCategoryId = Mage::app()->getStore($storeId)->getRootCategoryId();
        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('path', ['like' => "1/{$rootCategoryId}/%"]);

        // Add root category too
        $fields[] = 'cat_position_' . $rootCategoryId;

        foreach ($categories as $category) {
            $fields[] = 'cat_position_' . $category->getId();
        }

        return $fields;
    }

    /**
     * Get product positions within each category, including inherited parent category positions.
     *
     * Products are typically assigned to leaf categories only (e.g., "Adult Tennis Racquets" category 5).
     * When viewing a parent category (e.g., "Racquets" category 4), we need position data for sorting.
     * This method propagates positions to all parent categories in the path.
     *
     * @param int $productId
     * @param array $categoryIds Categories this product is assigned to
     * @return array [category_id => position] Including inherited positions for parent categories
     */
    private function getCategoryPositions($productId, array $categoryIds)
    {
        if (empty($categoryIds)) {
            return [];
        }

        $positions = [];
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $table = $resource->getTableName('catalog/category_product');

        // Get direct positions from catalog_category_product
        $select = $read->select()
            ->from($table, ['category_id', 'position'])
            ->where('product_id = ?', $productId)
            ->where('category_id IN (?)', $categoryIds);

        $results = $read->fetchPairs($select);

        foreach ($results as $catId => $position) {
            $positions[(int) $catId] = (int) $position;

            // Propagate position to all parent categories in the path
            // This allows sorting by position when viewing parent categories
            $category = Mage::getModel('catalog/category')->load($catId);
            $pathIds = explode('/', (string) $category->getPath());

            foreach ($pathIds as $parentCatId) {
                $parentCatId = (int) $parentCatId;
                // Skip root categories (1 = root, 2 = default store root)
                if ($parentCatId <= 2) {
                    continue;
                }
                // Don't overwrite if already set with a more specific position
                if (!isset($positions[$parentCatId])) {
                    $positions[$parentCatId] = (int) $position;
                }
            }
        }

        return $positions;
    }

    /**
     * Returns all parent product IDs, e.g. when simple product is part of configurable or bundle
     *
     * @return array
     */
    public function getParentProductIds(array $productIds)
    {
        $parentIds = [];
        foreach ($this->getCompositeTypes() as $typeInstance) {
            $parentIds = array_merge($parentIds, $typeInstance->getParentIdsByChild($productIds));
        }

        return $parentIds;
    }

    /**
     * Returns composite product type instances
     *
     * @return Mage_Catalog_Model_Product_Type[]
     *
     * @see Mage_Catalog_Model_Resource_Product_Flat_Indexer::getProductTypeInstances()
     */
    private function getCompositeTypes()
    {
        if ($this->compositeTypes === null) {
            /** @var Mage_Catalog_Model_Product $productEmulator */
            $productEmulator = Mage::getModel('catalog/product');

            /** @var Mage_Catalog_Model_Product_Type $productType */
            $productType = Mage::getModel('catalog/product_type');
            foreach ($productType->getCompositeTypes() as $typeId) {
                $productEmulator->setTypeId($typeId);
                $this->compositeTypes[$typeId] = $productType->factory($productEmulator);
            }
        }

        return $this->compositeTypes;
    }

    public function getAllProductIds($storeId)
    {
        $products = Mage::getModel('catalog/product')->getCollection();

        $products = $products->setStoreId($storeId);
        $products = $products->addStoreFilter($storeId);

        return $products->getAllIds();
    }

    public function shouldIndexProductByItsVisibility(Mage_Catalog_Model_Product $product, $storeId)
    {
        $productVisibility = (int) $product->getVisibility();
        $indexVisibility = $this->config->indexVisibility($storeId);

        $shouldIndex = ($productVisibility > Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);

        if ($indexVisibility === 'only_search') {
            $shouldIndex = ($productVisibility === Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH || $productVisibility === Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        } elseif ($indexVisibility === 'only_catalog') {
            $shouldIndex = ($productVisibility === Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG || $productVisibility === Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        }

        return $shouldIndex;
    }

    private function getVisibilityAttributeValues($storeId)
    {
        $indexVisibility = $this->config->indexVisibility($storeId);

        /** @var Mage_Catalog_Model_Product_Visibility $catalog_productVisibility */
        $catalog_productVisibility = Mage::getSingleton('catalog/product_visibility');

        $visibilityMethod = 'getVisibleInSiteIds';
        if ($indexVisibility === 'only_search') {
            $visibilityMethod = 'getVisibleInSearchIds';
        } elseif ($indexVisibility === 'only_catalog') {
            $visibilityMethod = 'getVisibleInCatalogIds';
        }

        return $catalog_productVisibility->{$visibilityMethod}();
    }

    /**
     * Check if product can be index on Meilisearch
     *
     * @param int     $storeId
     *
     * @return bool
     *
     */
    public function canProductBeReindexed(Mage_Catalog_Model_Product $product, $storeId)
    {
        if ($product->isDeleted() === true) {
            throw (new Meilisearch_Search_Model_Exception_ProductDeletedException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        if ($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
            throw (new Meilisearch_Search_Model_Exception_ProductDisabledException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        if ($this->shouldIndexProductByItsVisibility($product, $storeId) === false) {
            throw (new Meilisearch_Search_Model_Exception_ProductNotVisibleException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        if (!$this->config->getShowOutOfStock($storeId)
            && !$product->getStockItem()->getIsInStock()) {
            throw (new Meilisearch_Search_Model_Exception_ProductOutOfStockException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        return true;
    }

    private function explodeSynomyms($synonyms)
    {
        return array_map(trim(...), explode(',', $synonyms));
    }

    private function setNoAttributes($attributes)
    {
        foreach ($attributes as $attribute) {
            if (isset($attribute['index_no_value']) && $attribute['index_no_value'] !== '1') {
                $this->noAttributes[$attribute['attribute']] = 1;
            }
        }
    }

    private function clearNoValues($customData, $rootLevel = true)
    {
        foreach ($customData as $attribute => $value) {
            if (is_array($value) && $rootLevel && isset($this->noAttributes[$attribute])) {
                $customData[$attribute] = $this->clearNoValues($value, false);
            }

            if (($rootLevel !== true || isset($this->noAttributes[$attribute])) && $value === Mage::helper('catalog')->__('No')) {
                unset($customData[$attribute]);
            }
        }

        return $customData;
    }

    private function deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId)
    {
        $indicesToDelete = [];

        $allIndices = $this->meilisearch_helper->listIndexes();
        foreach ($allIndices['items'] as $indexInfo) {
            if (!str_starts_with((string) $indexInfo['name'], (string) $indexName) || $indexInfo['name'] === $indexName) {
                continue;
            }
            // Do not delete tmp indices which are used for indexing jobs
            if (!str_contains((string) $indexInfo['name'], '_tmp') && in_array($indexInfo['name'], $replicas) === false) {
                $indicesToDelete[] = $indexInfo['name'];
            }
        }

        if (count($indicesToDelete) > 0) {
            $this->meilisearch_helper->waitLastTask();

            foreach ($indicesToDelete as $indexToDelete) {
                $this->meilisearch_helper->deleteIndex($indexToDelete);
            }
        }
    }

    private function setFacetsQueryRules($indexName)
    {
        // Query rules are not supported in Meilisearch
        // This is an Algolia-specific feature
        return;
    }

    private function clearFacetsQueryRules($index)
    {
        // Query rules are not supported in Meilisearch
        // This is an Algolia-specific feature
        return;
    }

    /**
     * Get the list of attributes that should be displayed in search results
     *
     * @param int $storeId
     * @return array
     */
    public function getDisplayedAttributes($storeId)
    {
        $unretrievableAttributes = [];
        $additionalAttributes = $this->config->getProductAdditionalAttributes($storeId);

        // Get the list of attributes marked as non-retrievable
        foreach ($additionalAttributes as $attribute) {
            if ($attribute['retrievable'] != '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        // Build displayed attributes list (all attributes except those marked as non-retrievable)
        $allAttributes = $this->getAllAttributeNames($storeId);
        $displayedAttributes = array_diff($allAttributes, $unretrievableAttributes);

        // If no additional attributes are configured, add some sensible defaults
        if (empty($additionalAttributes)) {
            $defaults = ['description', 'sku', 'price', 'in_stock'];
            $displayedAttributes = array_merge($displayedAttributes, $defaults);
        }

        // Price is special - it's needed for display even if marked non-retrievable
        // The actual price values are in nested format (price.AUD.default, etc.)
        if (!in_array('price', $displayedAttributes)) {
            $displayedAttributes[] = 'price';
        }

        return array_values(array_unique($displayedAttributes));
    }
}
