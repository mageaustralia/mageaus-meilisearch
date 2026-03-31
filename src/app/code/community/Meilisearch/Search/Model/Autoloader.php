<?php

/**
 * Meilisearch Search extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Meilisearch
 * @package    Meilisearch_Search
 * @copyright  Copyright (c) 2024 Meilisearch (https://www.meilisearch.com/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// Load Composer autoloader for Meilisearch PHP SDK
$vendorAutoload = dirname(__FILE__, 6) . '/vendor/autoload.php';

if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    // Fallback to alternative vendor location
    $alternativeAutoload = dirname(__FILE__, 7) . '/vendor/autoload.php';
    if (file_exists($alternativeAutoload)) {
        require_once $alternativeAutoload;
    } else {
        throw new Exception('Meilisearch PHP SDK not found. Please install it using: composer require meilisearch/meilisearch-php');
    }
}
