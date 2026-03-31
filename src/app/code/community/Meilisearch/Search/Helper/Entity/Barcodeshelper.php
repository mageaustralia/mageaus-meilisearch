<?php

use MeilisearchSearch\MeilisearchException;

class Meilisearch_Search_Helper_Entity_Barcodeshelper extends Meilisearch_Search_Helper_Entity_Helper
{
    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    public function __construct()
    {
        parent::__construct();
    }

    protected function getIndexNameSuffix()
    {
        return '_barcodes';
    }

    /**
     * Get minimal attributes for barcode scanner
     * Only index what's needed: GTIN, name, price, URL, image
     */
    public function getMinimalAttributesForIndex()
    {
        return [
            'entity_id',
            'sku',
            'name',
            'gtin',
            'price',
            'special_price',
            'url_key',
            'image',
            'small_image',
            'thumbnail',
            'status',
        ];
    }

    public function getProductCollectionQuery($storeId, $productIds = null, $only_visible = true, $withoutData = false)
    {
        /** @var $products Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection */
        $products = Mage::getResourceModel('catalog/product_collection');

        $products = $products->setStoreId($storeId);
        $products = $products->addStoreFilter($storeId);
        $products = $products->distinct(true);

        if ($productIds && count($productIds) > 0) {
            $products = $products->addAttributeToFilter('entity_id', ['in' => $productIds]);
        }

        // Don't filter by visibility or status - include ALL products

        if ($withoutData === false) {
            // Only select the minimal attributes needed for barcode scanning
            $products = $products->addAttributeToSelect($this->getMinimalAttributesForIndex());
            $products->addPriceData();

            // Always use left join for price index to include products without prices
            $fromPart = $products->getSelect()->getPart(Varien_Db_Select::FROM);
            if (isset($fromPart['price_index'])) {
                $fromPart['price_index']['joinType'] = 'left join';
                $products->getSelect()->setPart(Varien_Db_Select::FROM, $fromPart);
            }
        }

        Mage::dispatchEvent('meilisearch_rebuild_store_barcodes_index_collection_load_before', ['store' => $storeId, 'collection' => $products]);
        Mage::dispatchEvent('meilisearch_after_barcodes_collection_build', ['store' => $storeId, 'collection' => $products]);

        return $products;
    }

    /**
     * Get minimal object data for barcode index
     *
     * @return array
     */
    public function getObject(Mage_Catalog_Model_Product $product)
    {
        $storeId = $product->getStoreId();
        $productToUse = $product;

        // If this is a simple product with a configurable parent, use parent's URL and image
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
                ->getParentIdsByChild($product->getId());

            if (!empty($parentIds)) {
                // Load the first parent (configurable)
                $parent = Mage::getModel('catalog/product')->setStoreId($storeId)->load($parentIds[0]);
                if ($parent->getId() && $parent->isVisibleInSiteVisibility()) {
                    $productToUse = $parent;
                }
            }
        }

        // Build the product URL (using parent if applicable)
        $productUrl = $productToUse->getProductUrl(false);
        if (!$productUrl) {
            $routeParams = [
                '_nosid' => true,
                '_type' => false,
                '_store' => $productToUse->getStore(),
            ];
            $productUrl = Mage::getUrl('catalog/product/view', array_merge($routeParams, ['id' => $productToUse->getId()]));
        }

        // Get the final price
        // For simple products with a configurable parent, use parent's pricing if simple doesn't have special price
        $price = null;
        $specialPrice = null;
        try {
            $price = $product->getPrice();
            $specialPrice = $product->getSpecialPrice();

            // If this is a simple product with a parent and no special price of its own,
            // check if the parent has a special price
            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE &&
                !$specialPrice &&
                $productToUse->getId() != $product->getId()) {

                // Use parent's special price if available
                $parentSpecialPrice = $productToUse->getSpecialPrice();
                if ($parentSpecialPrice) {
                    $specialPrice = $parentSpecialPrice;
                }
            }

            // Use final price if available (works for enabled products with price index)
            if ($product->getFinalPrice() && $product->getFinalPrice() != $product->getPrice()) {
                $price = $product->getFinalPrice();
            } elseif ($specialPrice && $specialPrice < $price) {
                // For disabled products or when final price isn't available,
                // manually use the cheaper of price vs special_price
                $price = $specialPrice;
            }
        } catch (Exception) {
            // Price might not be available for some products
            $price = 0;
        }

        // Get product image using configured size (typically 265x265) - use parent if applicable
        /** @var Meilisearch_Search_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('meilisearch_search/image');

        try {
            $image = $imageHelper->init($productToUse, $this->config->getImageType())
                         ->resize($this->config->getImageWidth(), $this->config->getImageHeight());
            $imageUrl = $image->toString();
        } catch (Exception) {
            // Use placeholder image if product image is not available
            $placeholderUrl = Mage::getDesign()->getSkinUrl($imageHelper->init($productToUse, $this->config->getImageType())->getPlaceholder());
            $imageUrl = $imageHelper->removeProtocol($placeholderUrl);
        }

        // Check if product is enabled (status = 1)
        $isEnabled = ($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        // Build minimal data object for barcode scanning
        $data = [
            'objectID' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName() ?: 'Product #' . $product->getId(),
            'gtin' => $product->getGtin() ?: $product->getSku(), // Fallback to SKU if no GTIN
            'price' => (float) $price,
            'url' => $productUrl,
            'image_url' => $imageUrl,
            'is_enabled' => $isEnabled,
        ];

        // Add special price if it exists and is different from regular price
        if ($specialPrice && $specialPrice != $price) {
            $data['special_price'] = (float) $specialPrice;
        }

        return $data;
    }


    /**
     * Set minimal index settings for barcode search
     */
    public function setSettings($storeId, $saveToTmpIndicesToo = false)
    {
        $indexName = $this->getIndexName($storeId);
        $indexNameTmp = $this->getIndexName($storeId, true);

        // Minimal settings focused on barcode/GTIN search
        $settings = [
            'searchableAttributes' => [
                'gtin',
                'sku',
                'name',
            ],
            'displayedAttributes' => [
                'objectID',
                'sku',
                'name',
                'gtin',
                'price',
                'special_price',
                'url',
                'image_url',
                'is_enabled',
            ],
            'sortableAttributes' => [
                'name',
                'price',
            ],
            'filterableAttributes' => [
                'price',
                'is_enabled',
            ],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
        ];

        try {
            $this->getMeilisearchHelper()->setSettings($indexName, $settings);

            if ($saveToTmpIndicesToo) {
                $this->getMeilisearchHelper()->setSettings($indexNameTmp, $settings);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            throw $e;
        }
    }

    /**
     * Get Meilisearch helper
     */
    protected function getMeilisearchHelper()
    {
        return Mage::helper('meilisearch_search/meilisearchhelper');
    }
}
