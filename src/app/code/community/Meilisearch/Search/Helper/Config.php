<?php

class Meilisearch_Search_Helper_Config extends Mage_Core_Helper_Abstract
{
    public const MINIMAL_QUERY_LENGTH = 'meilisearch/ui/minimal_query_length';
    public const SEARCH_DELAY = 'meilisearch/ui/search_delay';

    public const ENABLE_FRONTEND = 'meilisearch/credentials/enable_frontend';
    public const ENABLE_BACKEND = 'meilisearch/credentials/enable_backend';
    public const IS_POPUP_ENABLED = 'meilisearch/credentials/is_popup_enabled';
    public const SERVER_URL = 'meilisearch/credentials/server_url';
    public const SEARCH_RESULTS_URL = 'meilisearch/credentials/search_results_url';
    public const API_KEY = 'meilisearch/credentials/api_key';
    public const SEARCH_ONLY_API_KEY = 'meilisearch/credentials/search_only_api_key';
    public const INDEX_PREFIX = 'meilisearch/credentials/index_prefix';

    public const IS_INSTANT_ENABLED = 'meilisearch/credentials/is_instant_enabled';
    public const USE_ADAPTIVE_IMAGE = 'meilisearch/credentials/use_adaptive_image';

    public const REPLACE_CATEGORIES = 'meilisearch/instant/replace_categories';
    public const INSTANT_SELECTOR = 'meilisearch/instant/instant_selector';
    public const FACETS = 'meilisearch/instant/facets';
    public const SORTING_INDICES = 'meilisearch/instant/sorts';
    public const XML_ADD_TO_CART_ENABLE = 'meilisearch/instant/add_to_cart_enable';
    public const INFINITE_SCROLL_ENABLE = 'meilisearch/instant/infinite_scroll_enable';

    public const NB_OF_PRODUCTS_SUGGESTIONS = 'meilisearch/autocomplete/nb_of_products_suggestions';
    public const NB_OF_CATEGORIES_SUGGESTIONS = 'meilisearch/autocomplete/nb_of_categories_suggestions';
    public const NB_OF_QUERIES_SUGGESTIONS = 'meilisearch/autocomplete/nb_of_queries_suggestions';
    public const NB_OF_PAGES_SUGGESTIONS = 'meilisearch/autocomplete/nb_of_pages_suggestions';
    public const AUTOCOMPLETE_SECTIONS = 'meilisearch/autocomplete/sections';
    public const EXCLUDED_PAGES = 'meilisearch/autocomplete/excluded_pages';
    public const MIN_POPULARITY = 'meilisearch/autocomplete/min_popularity';
    public const MIN_NUMBER_OF_RESULTS = 'meilisearch/autocomplete/min_number_of_results';
    public const DISPLAY_SUGGESTIONS_CATEGORIES = 'meilisearch/autocomplete/display_categories_with_suggestions';
    public const RENDER_TEMPLATE_DIRECTIVES = 'meilisearch/autocomplete/render_template_directives';
    public const AUTOCOMPLETE_MENU_DEBUG = 'meilisearch/autocomplete/debug';

    public const NUMBER_OF_PRODUCT_RESULTS = 'meilisearch/products/number_product_results';
    public const PRODUCT_ATTRIBUTES = 'meilisearch/products/product_additional_attributes';
    public const PRODUCT_CUSTOM_RANKING = 'meilisearch/products/custom_ranking_product_attributes';
    public const RESULTS_LIMIT = 'meilisearch/products/results_limit';
    public const SHOW_SUGGESTIONS_NO_RESULTS = 'meilisearch/products/show_suggestions_on_no_result_page';
    public const INDEX_VISIBILITY = 'meilisearch/products/index_visibility';
    public const INDEX_OUT_OF_STOCK_OPTIONS = 'meilisearch/products/index_out_of_stock_options';
    public const INDEX_WHOLE_CATEGORY_TREE = 'meilisearch/products/index_whole_category_tree';

    public const CATEGORY_ATTRIBUTES = 'meilisearch/categories/category_additional_attributes2';
    public const CATEGORY_CUSTOM_RANKING = 'meilisearch/categories/custom_ranking_category_attributes';
    public const SHOW_CATS_NOT_INCLUDED_IN_NAVIGATION = 'meilisearch/categories/show_cats_not_included_in_navigation';
    public const INDEX_EMPTY_CATEGORIES = 'meilisearch/categories/index_empty_categories';

    public const IS_ACTIVE = 'meilisearch/queue/active';
    public const NUMBER_OF_ELEMENT_BY_PAGE = 'meilisearch/queue/number_of_element_by_page';
    public const NUMBER_OF_JOB_TO_RUN = 'meilisearch/queue/number_of_job_to_run';
    public const RETRY_LIMIT = 'meilisearch/queue/number_of_retries';
    public const CHECK_PRICE_INDEX = 'meilisearch/queue/check_price_index';
    public const CHECK_STOCK_INDEX = 'meilisearch/queue/check_stock_index';

    public const XML_PATH_IMAGE_WIDTH = 'meilisearch/image/width';
    public const XML_PATH_IMAGE_HEIGHT = 'meilisearch/image/height';
    public const XML_PATH_IMAGE_TYPE = 'meilisearch/image/type';
    public const XML_PATH_PREFIX = 'meilisearch/';

    public const ENABLE_SYNONYMS = 'meilisearch/synonyms/enable_synonyms';
    public const SYNONYMS = 'meilisearch/synonyms/synonyms';
    public const ONEWAY_SYNONYMS = 'meilisearch/synonyms/oneway_synonyms';
    public const SYNONYMS_FILE = 'meilisearch/synonyms/synonyms_file';

    public const CUSTOMER_GROUPS_ENABLE = 'meilisearch/advanced/customer_groups_enable';
    public const MAKE_SEO_REQUEST = 'meilisearch/advanced/make_seo_request';
    public const SHOW_QUEUE_NOTIFICATION = 'meilisearch/advanced/show_queue_notification';
    public const AUTOCOMPLETE_SELECTOR = 'meilisearch/advanced/autocomplete_selector';
    public const INDEX_PRODUCT_ON_CATEGORY_PRODUCTS_UPDATE = 'meilisearch/advanced/index_product_on_category_products_update';
    public const INDEX_ALL_CATEGORY_PRODUCTS_ON_CATEGORY_UPDATE = 'meilisearch/advanced/index_all_category_product_on_category_update';
    public const PREVENT_BACKEND_RENDERING = 'meilisearch/advanced/prevent_backend_rendering';
    public const PREVENT_BACKEND_RENDERING_DISPLAY_MODE = 'meilisearch/advanced/prevent_backend_rendering_display_mode';
    public const BACKEND_RENDERING_ALLOWED_USER_AGENTS = 'meilisearch/advanced/backend_rendering_allowed_user_agents';
    public const NON_CASTABLE_ATTRIBUTES = 'meilisearch/advanced/non_castable_attributes';
    public const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';
    public const LOGGING_ENABLED = 'meilisearch/credentials/debug';

    public const EXTRA_SETTINGS_PRODUCTS = 'meilisearch/advanced_settings/products_extra_settings';
    public const EXTRA_SETTINGS_CATEGORIES = 'meilisearch/advanced_settings/categories_extra_settings';
    public const EXTRA_SETTINGS_PAGES = 'meilisearch/advanced_settings/pages_extra_settings';
    public const EXTRA_SETTINGS_SUGGESTIONS = 'meilisearch/advanced_settings/suggestions_extra_settings';
    public const EXTRA_SETTINGS_ADDITIONAL_SECTIONS = 'meilisearch/advanced_settings/additional_sections_extra_settings';

    protected $_productTypeMap = [];

    public function indexVisibility($storeId = null)
    {
        return Mage::getStoreConfig(self::INDEX_VISIBILITY, $storeId);
    }

    public function indexOutOfStockOptions($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::INDEX_OUT_OF_STOCK_OPTIONS, $storeId);
    }

    public function indexWholeCategoryTree($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::INDEX_WHOLE_CATEGORY_TREE, $storeId);
    }

    public function showCatsNotIncludedInNavigation($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::SHOW_CATS_NOT_INCLUDED_IN_NAVIGATION, $storeId);
    }

    public function shouldIndexEmptyCategories($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::INDEX_EMPTY_CATEGORIES, $storeId);
    }

    public function isDefaultSelector($storeId = null)
    {
        return '.meilisearch-search-input' === $this->getAutocompleteSelector($storeId);
    }

    public function getAutocompleteSelector($storeId = null)
    {
        return Mage::getStoreConfig(self::AUTOCOMPLETE_SELECTOR, $storeId);
    }

    public function indexProductOnCategoryProductsUpdate($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::INDEX_PRODUCT_ON_CATEGORY_PRODUCTS_UPDATE, $storeId);
    }

    public function indexAllCategoryProductsOnCategoryUpdate($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::INDEX_ALL_CATEGORY_PRODUCTS_ON_CATEGORY_UPDATE, $storeId);
    }

    public function getNumberOfQueriesSuggestions($storeId = null)
    {
        return Mage::getStoreConfig(self::NB_OF_QUERIES_SUGGESTIONS, $storeId);
    }

    public function getNumberOfProductsSuggestions($storeId = null)
    {
        return Mage::getStoreConfig(self::NB_OF_PRODUCTS_SUGGESTIONS, $storeId);
    }

    public function getNumberOfCategoriesSuggestions($storeId = null)
    {
        return Mage::getStoreConfig(self::NB_OF_CATEGORIES_SUGGESTIONS, $storeId);
    }

    public function getNumberOfPagesSuggestions($storeId = null)
    {
        return Mage::getStoreConfig(self::NB_OF_PAGES_SUGGESTIONS, $storeId) ?: 2;
    }

    public function showSuggestionsOnNoResultsPage($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::SHOW_SUGGESTIONS_NO_RESULTS, $storeId);
    }

    public function displaySuggestionsCategories($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::DISPLAY_SUGGESTIONS_CATEGORIES, $storeId);
    }

    public function isEnabledFrontEnd($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::ENABLE_FRONTEND, $storeId);
    }

    public function isEnabledBackend($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::ENABLE_BACKEND, $storeId);
    }

    public function makeSeoRequest($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::MAKE_SEO_REQUEST, $storeId);
    }

    public function isLoggingEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::LOGGING_ENABLED, $storeId);
    }

    public function getShowOutOfStock($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::SHOW_OUT_OF_STOCK, $storeId);
    }

    public function getImageWidth($storeId = null)
    {
        $imageWidth = Mage::getStoreConfig(self::XML_PATH_IMAGE_WIDTH, $storeId);
        if (empty($imageWidth)) {
            return;
        }

        return $imageWidth;
    }

    public function getImageHeight($storeId = null)
    {
        $imageHeight = Mage::getStoreConfig(self::XML_PATH_IMAGE_HEIGHT, $storeId);
        if (empty($imageHeight)) {
            return;
        }

        return $imageHeight;
    }

    public function getImageType($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_IMAGE_TYPE, $storeId);
    }

    public function isCustomerGroupsEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::CUSTOMER_GROUPS_ENABLE, $storeId);
    }

    /**
     * Safe unserialize method that tries multiple formats
     */
    private function safeUnserialize($data, $context = 'unknown')
    {
        if ($data === null || $data === '') {
            return [];
        }

        try {
            // Try PHP unserialize first (Maho 25.11.0+: Zend removed)
            $result = @unserialize($data);
            if ($result !== false || $data === 'b:0;') {
                return $result;
            }
        } catch (Exception $e) {
            // Continue to JSON fallback
        }

        try {
            // Fallback to JSON decode
            $decoded = json_decode((string) $data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        } catch (Exception) {
            // Continue to error handling
        }

        // Log the error and return empty array
        Mage::log("Failed to unserialize $context config: " . $data, Mage::LOG_WARNING);
        return [];
    }

    public function getAutocompleteSections($storeId = null)
    {
        $config = Mage::getStoreConfig(self::AUTOCOMPLETE_SECTIONS, $storeId);
        $attrs = $this->safeUnserialize($config, 'autocomplete sections');

        if (is_array($attrs)) {
            // Filter out sections for modules that aren't installed
            $validSections = [];
            foreach ($attrs as $section) {
                // Check if this is amasty_pages and if Amasty Shopby is installed
                if (isset($section['name']) && $section['name'] === 'amasty_pages') {
                    if (!$this->isAmastyShopbyInstalled()) {
                        continue; // Skip this section
                    }
                }
                $validSections[] = $section;
            }
            return array_values($validSections);
        }

        return [];
    }

    /**
     * Check if Amasty Shopby module is installed and active
     */
    protected function isAmastyShopbyInstalled()
    {
        $modules = (array) Mage::getConfig()->getNode('modules')->children();
        return isset($modules['Amasty_Shopby']) && $modules['Amasty_Shopby']->is('active');
    }

    public function getMinPopularity($storeId = null)
    {
        return Mage::getStoreConfig(self::MIN_POPULARITY, $storeId);
    }

    public function getMinNumberOfResults($storeId = null)
    {
        return Mage::getStoreConfig(self::MIN_NUMBER_OF_RESULTS, $storeId);
    }

    public function isAddToCartEnable($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_ADD_TO_CART_ENABLE, $storeId);
    }

    public function isInfiniteScrollEnabled($storeId = null)
    {
        return $this->isInstantEnabled($storeId)
            && Mage::getStoreConfigFlag(self::INFINITE_SCROLL_ENABLE, $storeId);
    }

    public function showQueueNotificiation($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::SHOW_QUEUE_NOTIFICATION, $storeId);
    }

    public function getNumberOfElementByPage($storeId = null)
    {
        return Mage::getStoreConfig(self::NUMBER_OF_ELEMENT_BY_PAGE, $storeId);
    }

    public function getNumberOfJobToRun($storeId = null)
    {
        return Mage::getStoreConfig(self::NUMBER_OF_JOB_TO_RUN, $storeId);
    }

    public function isQueueActive($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::IS_ACTIVE, $storeId);
    }

    public function shouldCheckPriceIndex($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::CHECK_PRICE_INDEX, $storeId);
    }

    public function shouldCheckStockIndex($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::CHECK_STOCK_INDEX, $storeId);
    }

    public function getRetryLimit($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::RETRY_LIMIT, $storeId);
    }

    public function getNumberOfProductResults($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::NUMBER_OF_PRODUCT_RESULTS, $storeId);
    }

    public function getResultsLimit($storeId = null)
    {
        return Mage::getStoreConfig(self::RESULTS_LIMIT, $storeId);
    }

    /**
     * Whether the autocomplete / popup search dropdown is enabled.
     *
     * The underlying config key is `meilisearch/credentials/is_popup_enabled`
     * for historical reasons — the feature was originally called "popup
     * search" and the DB path was never renamed. It controls what users
     * today know as the autocomplete dropdown that appears under the
     * header search input. `isAutoCompleteEnabled()` is an alias kept for
     * readability at call sites.
     */
    public function isPopupEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::IS_POPUP_ENABLED, $storeId);
    }

    public function replaceCategories($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::REPLACE_CATEGORIES, $storeId);
    }

    /** Alias for {@see isPopupEnabled()} — same underlying flag. */
    public function isAutoCompleteEnabled($storeId = null)
    {
        return $this->isPopupEnabled($storeId);
    }

    public function isInstantEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::IS_INSTANT_ENABLED, $storeId);
    }

    /**
     * Max facet values returned per facet by instant search.
     * The configuration.phtml template calls this but the method was never
     * defined, which fatals the block and prevents the inline
     * window.meilisearchConfig script from being emitted — breaking frontend
     * search entirely. Default 100 matches Algolia/Meilisearch library defaults.
     */
    public function getMaxValuesPerFacet($storeId = null)
    {
        $v = (int) Mage::getStoreConfig('meilisearch/instant/max_values_per_facet', $storeId);
        return $v > 0 ? $v : 100;
    }

    /**
     * Analytics toggles — the configuration.phtml template references these
     * but the methods were never implemented on Config.php, which fatals the
     * block and prevents window.meilisearchConfig from being emitted.
     * Safe defaults: analytics off, no initial-search push, no UI-interaction
     * trigger, 3s delay.
     */
    public function isEnabledAnalytics($storeId = null)
    {
        return Mage::getStoreConfigFlag('meilisearch/analytics/enabled', $storeId);
    }

    public function getAnalyticsDelay($storeId = null)
    {
        $v = (int) Mage::getStoreConfig('meilisearch/analytics/delay', $storeId);
        return $v > 0 ? $v : 3000;
    }

    public function getPushInitialSearch($storeId = null)
    {
        return Mage::getStoreConfigFlag('meilisearch/analytics/push_initial_search', $storeId);
    }

    public function getTriggerOnUIInteraction($storeId = null)
    {
        return Mage::getStoreConfigFlag('meilisearch/analytics/trigger_on_ui_interaction', $storeId);
    }

    public function useAdaptiveImage($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::USE_ADAPTIVE_IMAGE, $storeId);
    }

    public function getInstantSelector($storeId = null)
    {
        return Mage::getStoreConfig(self::INSTANT_SELECTOR, $storeId);
    }

    public function getExcludedPages($storeId = null)
    {
        $config = Mage::getStoreConfig(self::EXCLUDED_PAGES, $storeId);
        $attrs = $this->safeUnserialize($config, 'excluded pages');

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getRenderTemplateDirectives($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::RENDER_TEMPLATE_DIRECTIVES, $storeId);
    }

    public function isAutocompleteDebugEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::AUTOCOMPLETE_MENU_DEBUG, $storeId);
    }

    public function getSortingIndices($storeId = null)
    {
        /** @var Meilisearch_Search_Helper_Entity_Producthelper $product_helper */
        $product_helper = Mage::helper('meilisearch_search/entity_producthelper');

        $config = Mage::getStoreConfig(self::SORTING_INDICES, $storeId);
        $attrs = $this->safeUnserialize($config, 'sorting indices');

        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');
        $group_id = $customerSession->getCustomerGroupId();

        foreach ($attrs as &$attr) {
            if ($this->isCustomerGroupsEnabled($storeId)) {
                if (str_contains((string) $attr['attribute'], 'price')) {
                    $suffix_index_name = 'group_' . $group_id;

                    $attr['name'] = $product_helper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $suffix_index_name . '_' . $attr['sort'];
                } else {
                    $attr['name'] = $product_helper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $attr['sort'];
                }
            } else {
                if (str_contains((string) $attr['attribute'], 'price')) {
                    $attr['name'] = $product_helper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . 'default' . '_' . $attr['sort'];
                } else {
                    $attr['name'] = $product_helper->getIndexName($storeId) . '_' . $attr['attribute'] . '_' . $attr['sort'];
                }
            }
        }

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getServerUrl($storeId = null)
    {
        return trim((string) Mage::getStoreConfig(self::SERVER_URL, $storeId));
    }

    public function getSearchResultsUrl($storeId = null)
    {
        $customUrl = trim((string) Mage::getStoreConfig(self::SEARCH_RESULTS_URL, $storeId));
        return $customUrl ?: '/meilisearch'; // Default to /meilisearch if not configured
    }

    public function getAPIKey($storeId = null)
    {
        $apiKey = Mage::getStoreConfig(self::API_KEY, $storeId);

        // Decrypt the API key if it's encrypted
        if ($apiKey) {
            /** @var Mage_Core_Helper_Data $coreHelper */
            $coreHelper = Mage::helper('core');
            $apiKey = $coreHelper->decrypt($apiKey);
        }

        return trim($apiKey);
    }

    public function getSearchOnlyAPIKey($storeId = null)
    {
        $apiKey = Mage::getStoreConfig(self::SEARCH_ONLY_API_KEY, $storeId);

        // Decrypt the API key if it's encrypted
        if ($apiKey) {
            /** @var Mage_Core_Helper_Data $coreHelper */
            $coreHelper = Mage::helper('core');
            $apiKey = $coreHelper->decrypt($apiKey);
        }

        return trim($apiKey);
    }

    public function getIndexPrefix($storeId = null)
    {
        return trim((string) Mage::getStoreConfig(self::INDEX_PREFIX, $storeId));
    }

    public function getAttributesToRetrieve($groupId, $store)
    {
        if (false === $this->isCustomerGroupsEnabled()) {
            return [];
        }

        $attributes = [];
        foreach ($this->getProductAdditionalAttributes() as $attribute) {
            if ($attribute['attribute'] !== 'price' && $attribute['retrievable'] === '1') {
                $attributes[] = $attribute['attribute'];
            }
        }

        foreach ($this->getCategoryAdditionalAttributes() as $attribute) {
            if ($attribute['retrievable'] === '1') {
                $attributes[] = $attribute['attribute'];
            }
        }

        $attributes = array_merge($attributes, [
            'objectID',
            'name',
            'url',
            'visibility_search',
            'visibility_catalog',
            'categories',
            'categories_without_path',
            'thumbnail_url',
            'image_url',
            'in_stock',
            'type_id',
            'value', // for additional sections
        ]);

        /** @var Mage_Directory_Model_Currency $currencyDirectory */
        $currencyDirectory = Mage::getModel('directory/currency');
        $currencies = $currencyDirectory->getConfigAllowCurrencies();

        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        $priceFields = ['price'];

        if ($taxHelper->getPriceDisplayType($store) == Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH) {
            $priceFields[] = 'price_with_tax';
        }

        foreach ($priceFields as $price) {
            foreach ($currencies as $currency) {
                $attributes[] = $price . '.' . $currency . '.default';
                $attributes[] = $price . '.' . $currency . '.default_formated';
                $attributes[] = $price . '.' . $currency . '.group_' . $groupId;
                $attributes[] = $price . '.' . $currency . '.group_' . $groupId . '_formated';
                $attributes[] = $price . '.' . $currency . '.group_' . $groupId . '_original_formated';
                $attributes[] = $price . '.' . $currency . '.special_from_date';
                $attributes[] = $price . '.' . $currency . '.special_to_date';
            }
        }

        $attributes = array_unique($attributes);

        return array_values($attributes);
    }

    public function getCategoryAdditionalAttributes($storeId = null)
    {
        $config = Mage::getStoreConfig(self::CATEGORY_ATTRIBUTES, $storeId);
        $attrs = $this->safeUnserialize($config, 'category attributes');

        if (is_array($attrs)) {
            return $attrs;
        }

        return [];
    }

    public function getProductAdditionalAttributes($storeId = null)
    {
        $attributes = [];
        $config = Mage::getStoreConfig(self::PRODUCT_ATTRIBUTES, $storeId);
        $attributes = $this->safeUnserialize($config, 'product attributes');

        $facets = [];
        $config = Mage::getStoreConfig(self::FACETS, $storeId);
        $facets = $this->safeUnserialize($config, 'facets');
        $attributes = $this->addIndexableAttributes($attributes, $facets, '0');

        $sorts = [];
        $config = Mage::getStoreConfig(self::SORTING_INDICES, $storeId);
        $sorts = $this->safeUnserialize($config, 'sorting indices');
        $attributes = $this->addIndexableAttributes($attributes, $sorts, '0');

        $customRankings = [];
        $config = Mage::getStoreConfig(self::PRODUCT_CUSTOM_RANKING, $storeId);
        $customRankings = $this->safeUnserialize($config, 'product custom ranking');
        $customRankings = array_filter($customRankings, fn($customRanking) => $customRanking['attribute'] != 'custom_attribute');
        $attributes = $this->addIndexableAttributes($attributes, $customRankings, '0', '0');


        if (is_array($attributes)) {
            return $attributes;
        }

        return [];
    }

    public function getFacets($storeId = null)
    {
        $config = Mage::getStoreConfig(self::FACETS, $storeId);
        $attrs = $this->safeUnserialize($config, 'facets');

        foreach ($attrs as &$attr) {
            if (isset($attr['type']) && $attr['type'] == 'other') {
                $attr['type'] = $attr['other_type'] ?? 'checkbox';
            }
        }

        if (is_array($attrs)) {
            return array_values($attrs);
        }

        return [];
    }

    public function getCategoryCustomRanking($storeId = null)
    {
        return $this->getCustomRanking(self::CATEGORY_CUSTOM_RANKING, $storeId);
    }

    public function getProductCustomRanking($storeId = null)
    {
        return $this->getCustomRanking(self::PRODUCT_CUSTOM_RANKING, $storeId);
    }

    public function getRankingRules($storeId = null)
    {
        $value = Mage::getStoreConfig(self::XML_PATH_PREFIX . 'products/ranking_rules', $storeId);

        if (empty($value)) {
            // Return default MeiliSearch ranking rules
            return [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ];
        }

        // Parse the textarea value (one rule per line)
        $rules = explode("\n", (string) $value);
        $cleanRules = [];

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (!empty($rule)) {
                $cleanRules[] = $rule;
            }
        }

        return $cleanRules;
    }

    public function getCurrency($storeId = null)
    {
        $currencySymbol = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())
                              ->getSymbol();

        return $currencySymbol;
    }

    public function getPopularQueries($storeId = null)
    {
        if (!$this->showSuggestionsOnNoResultsPage($storeId)) {
            return [];
        }

        if ($storeId === null) {
            $storeId = Mage::app()->getStore()->getId();
        }

        /** @var Meilisearch_Search_Helper_Entity_Suggestionhelper $suggestionHelper */
        $suggestionHelper = Mage::helper('meilisearch_search/entity_suggestionhelper');
        $popularQueries = $suggestionHelper->getPopularQueries($storeId);

        return $popularQueries;
    }

    /**
     * Loads product type mapping from configuration (default) > meilisearch > product_map > (product type).
     *
     * @param $originalType
     *
     * @return string
     */
    public function getMappedProductType($originalType)
    {
        if (!isset($this->_productTypeMap[$originalType])) {
            $mappedType = (string) Mage::app()->getConfig()->getNode('default/meilisearch/product_map/' . $originalType);

            if ($mappedType) {
                $this->_productTypeMap[$originalType] = $mappedType;
            } else {
                $this->_productTypeMap[$originalType] = $originalType;
            }
        }

        return $this->_productTypeMap[$originalType];
    }

    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getNode()->modules->Meilisearch_Search->version;
    }

    public function isEnabledSynonyms($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::ENABLE_SYNONYMS, $storeId);
    }

    public function getSynonyms($storeId = null)
    {
        $synonyms = [];
        $config = Mage::getStoreConfig(self::SYNONYMS, $storeId);
        $synonyms = $this->safeUnserialize($config, 'synonyms');

        if (is_array($synonyms)) {
            return $synonyms;
        }

        return [];
    }

    public function getOnewaySynonyms($storeId = null)
    {
        $onewaySynonyms = [];
        $config = Mage::getStoreConfig(self::ONEWAY_SYNONYMS, $storeId);
        $onewaySynonyms = $this->safeUnserialize($config, 'oneway synonyms');

        if (is_array($onewaySynonyms)) {
            return $onewaySynonyms;
        }

        return [];
    }

    public function getSynonymsFile($storeId = null)
    {
        $filename = Mage::getStoreConfig(self::SYNONYMS_FILE, $storeId);
        if (!$filename) {
            return;
        }

        return Mage::getBaseDir('media') . '/meilisearch-admin-config-uploads/' . $filename;
    }

    public function getExtraSettings($section, $storeId = null)
    {
        $constant = 'EXTRA_SETTINGS_' . mb_strtoupper((string) $section);

        return trim((string) Mage::getStoreConfig(constant('self::' . $constant), $storeId));
    }

    public function preventBackendRendering($storeId = null)
    {
        $preventBackendRendering = Mage::getStoreConfigFlag(self::PREVENT_BACKEND_RENDERING, $storeId);

        if ($preventBackendRendering === false) {
            return false;
        }

        $userAgent = mb_strtolower((string) $_SERVER['HTTP_USER_AGENT'], 'utf-8');

        $allowedUserAgents = Mage::getStoreConfig(self::BACKEND_RENDERING_ALLOWED_USER_AGENTS, $storeId);
        $allowedUserAgents = trim($allowedUserAgents);

        if ($allowedUserAgents === '') {
            return true;
        }

        $allowedUserAgents = explode("\n", $allowedUserAgents);

        foreach ($allowedUserAgents as $allowedUserAgent) {
            $allowedUserAgent = mb_strtolower($allowedUserAgent, 'utf-8');
            if (str_contains($userAgent, $allowedUserAgent)) {
                return false;
            }
        }

        return true;
    }

    public function getBackendRenderingDisplayMode($storeId = null)
    {
        return Mage::getStoreConfig(self::PREVENT_BACKEND_RENDERING_DISPLAY_MODE, $storeId);
    }

    public function getNonCastableAttributes($storeId = null)
    {
        $nonCastableAttributes = [];
        $config = Mage::getStoreConfig(self::NON_CASTABLE_ATTRIBUTES, $storeId);
        $config = $this->safeUnserialize($config, 'non-castable attributes');

        if (is_array($config)) {
            foreach ($config as $attributeData) {
                if (isset($attributeData['attribute'])) {
                    $nonCastableAttributes[] = $attributeData['attribute'];
                }
            }
        }

        return $nonCastableAttributes;
    }

    private function getCustomRanking($configName, $storeId = null)
    {
        $attrs = [];
        $config = Mage::getStoreConfig($configName, $storeId);
        $attrs = $this->safeUnserialize($config, 'custom ranking');

        if (is_array($attrs)) {
            foreach ($attrs as $index => $attr) {
                if ($attr['attribute'] == 'custom_attribute') {
                    $attrs[$index]['attribute'] = $attr['custom_attribute'];
                }
            }

            return $attrs;
        }

        return [];
    }

    private function addIndexableAttributes($attributes, $addedAttributes, $searchable = '1', $retrievable = '1', $indexNoValue = '1')
    {
        foreach ((array) $addedAttributes as $addedAttribute) {
            foreach ((array) $attributes as $attribute) {
                if ($addedAttribute['attribute'] == $attribute['attribute']) {
                    continue 2;
                }
            }

            $attributes[] = [
                'attribute'         => $addedAttribute['attribute'],
                'searchable'        => $searchable,
                'retrievable'       => $retrievable,
                'index_no_value'    => $indexNoValue,
            ];
        }

        return $attributes;
    }

}
