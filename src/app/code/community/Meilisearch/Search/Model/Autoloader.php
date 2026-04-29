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

// If the project's composer autoloader already loaded the SDK there's nothing
// to do — Maho's bootstrap requires vendor/autoload.php long before this file
// runs. Cheap class_exists check avoids the brittle path-walk below in the
// common case (composer-installed module, project-level vendor/).
if (class_exists(\Meilisearch\Client::class, true)) {
    return;
}

// Otherwise walk up from this file looking for a vendor/autoload.php. The
// number of levels depends on where the module ended up:
//   6 — legacy app/code/community layout (Magento/Maho convention)
//   7 — same, but project root one extra level up (e.g. /app/app/code/...)
//   9 — composer-installed in vendor/<vendor>/<pkg>/src/app/code/community/...
// We scan a generous range so future install layouts don't regress this.
$base = __FILE__;
for ($i = 1; $i <= 10; $i++) {
    $candidate = dirname($base, $i) . '/vendor/autoload.php';
    if (is_file($candidate)) {
        require_once $candidate;
        if (class_exists(\Meilisearch\Client::class, true)) {
            return;
        }
    }
}

throw new RuntimeException(
    'Meilisearch PHP SDK not found. Searched vendor/autoload.php up to 10 '
    . 'levels above ' . __FILE__ . '. Run `composer require meilisearch/meilisearch-php` '
    . 'in your project root.',
);
