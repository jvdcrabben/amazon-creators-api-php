# Amazon Creators API Client (PHP 7 Compatible)

A lightweight, zero-dependency PHP client for the unified **Amazon Creators API**. 

## Why This Exists

Amazon's official PHP SDK for the Creators API strictly requires **PHP 8.1 or higher** and forces a heavy footprint of external Composer dependencies (such as modern Guzzle environments). 

For production environments locked into **PHP 7.x**, or legacy systems where adding large dependency trees isn't viable, neither the official SDK nor other third-party SDKs can be used. This was the case for [World History Encyclopedia](https://www.worldhistory.org), for which we initially created this library since using the old Product Advertising API for over 17 years.

This repository provides a **single-file, zero-dependency alternative** built entirely on native PHP `cURL` functions. It is fully backwards-compatible with PHP 7 while perfectly mapping the modern structural requirements of the Amazon Creators API.

### Key Features
* **PHP 7.0+ Fully Compatible:** Employs syntax and patterns safe for older PHP environments.
* **Zero External Dependencies:** No Composer required at runtime. Uses native PHP cURL.
* **Smart Token Caching:** Built-in automatic OAuth 2.0 access token caching using standard system temp file storage (`sys_get_temp_dir()`). This prevents hitting token generation rate limits on high-traffic sites.
* **Dual-Credential Support:** Automatically detects and routes both legacy **v2.x** (Cognito/Single-slash scope) and modern **v3.x** (LWA/Double-colon scope) Amazon credentials.
* **Automatic Regional Routing:** Automatically maps target marketplaces to correct Amazon regional OAuth token gateways (NA, EU, and FE gateways).

---

## Installation

Simply copy the `amazon-creators-api.php` class file into your project structure and include it:

```php
require_once __DIR__ . '/path/to/amazon-creators-api.php';
```

## Initialization

Instantiate the client by passing your Amazon Associates Creator credentials, version string, and target marketplace domain.

```PHP
$amazon = new AmazonCreatorsApiClient(
    'amzn1.application-oa2-v1.xxxxxxxxxxxxxxxxxxxx',    // Credential ID
    'amzn1.oa2-cs.v1.xxxxxxxxxxxxxxxxxxxxxxxxxxxx',     // Credential Secret
    'v3.1',                                             // Credential Version
    'www.amazon.com'                                    // Target Marketplace
);
```
⚠️ CRITICAL PAYLOAD NOTE: Unlike the legacy PA-API 5.0 which used PascalCase, the new Creators API expects all JSON keys and resource strings to be formatted in lowerCamelCase (e.g., itemInfo.title, offersV2.listings.price).

## API Reference

For more information on each of the methods, please refer to the [Amazon Creators API Reference](https://affiliate-program.amazon.com/creatorsapi/docs/en-us/api-reference).

## Method Examples

### GetItems

Retrieve detailed metadata, images, and pricing for specific product ASINs.

```PHP
try {
    $payload = [
        'itemIds'    => ['0023456108', '1400031702'],
        'partnerTag' => 'your-affiliate-tag-20',
        'resources'  => [
            'itemInfo.title',
            'itemInfo.externalIds',
            'itemInfo.byLineInfo',
            'itemInfo.productInfo',
            'itemInfo.features',
            'itemInfo.contentInfo',
            'images.primary.small',
            'images.primary.medium',
            'images.primary.large',
            'offersV2.listings.price',
            'offersV2.listings.availability'
        ]
    ];

    $response = $amazon->GetItems($payload);
    print_r($response);
} catch (Exception $e) {
    echo "GetItems Error: " . $e->getMessage();
}
```

### SearchItems

Search for products across the Amazon catalog matching specific keywords or criteria.

```PHP
try {
    $payload = [
        'keywords'    => 'history of rome',
        'partnerTag'  => 'your-affiliate-tag-20',
        'resources'   => [
            'itemInfo.title',
            'itemInfo.externalIds',
            'itemInfo.byLineInfo',
            'itemInfo.productInfo',
            'itemInfo.features',
            'itemInfo.contentInfo',
            'images.primary.small',
            'images.primary.medium',
            'images.primary.large',
            'offersV2.listings.price',
            'offersV2.listings.availability'
        ]
    ];

    $response = $amazon->SearchItems($payload);
    print_r($response);
} catch (Exception $e) {
    echo "SearchItems Error: " . $e->getMessage();
}
```

### GetBrowseNodes

Look up details about Amazon's structural category hierarchy (Browse Nodes).

```PHP
try {
    $payload = [
        'browseNodeIds' => ['4935', '283155'],
        'partnerTag'    => 'your-affiliate-tag-20',
        'resources'     => [
            'browseNodeInfo.browseNodes.ancestor',
            'browseNodeInfo.browseNodes.displayName'
        ]
    ];

    $response = $amazon->GetBrowseNodes($payload);
    print_r($response);
} catch (Exception $e) {
    echo "GetBrowseNodes Error: " . $e->getMessage();
}
```

### GetVariations

Retrieve variation parent/child matrix details for products with multiple options (like sizes, colors, or alternative editions).

```PHP
try {
    $payload = [
        'asin'       => 'B07XJ8C84Z',
        'partnerTag' => 'your-affiliate-tag-20',
        'resources'  => [
            'variationSummary.variationDimensionOptions',
            'itemInfo.title',
            'offersV2.listings.price'
        ]
    ];

    $response = $amazon->GetVariations($payload);
    print_r($response);
} catch (Exception $e) {
    echo "GetVariations Error: " . $e->getMessage();
}
```

## Important Architectural Notes

### Account Mapping and Regional Restrictions

The Creators API enforces strict credential scoping. Your Credential ID and Credential Secret are mathematically locked to the specific regional dashboard where they were created.

If you want to request data from www.amazon.co.uk, you must use credentials generated from your UK Amazon Associates dashboard alongside your UK tracking tag. Passing a UK tag with a US client ID will yield a standard mapping rejection error.

### Security Compliance

All communication routes over secure TLS tunnels directly to https://creatorsapi.amazon. No proxy servers or third-party gateways are utilized, keeping your sensitive API credentials and customer tracking payload endpoints strictly confidential.

## License
This project is dedicated to the public domain under the Creative Commons Zero v1.0 Universal (CC0 1.0) status.

You can copy, modify, distribute, and perform the work, even for commercial purposes, all without asking permission.
