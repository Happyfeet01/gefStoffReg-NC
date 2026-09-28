<?php
declare(strict_types=1);

return ['routes' => [
    ['name' => 'inventory#index', 'url' => '/', 'verb' => 'GET'],
    ['name' => 'inventory#bootstrap', 'url' => '/api/bootstrap', 'verb' => 'GET'],
    ['name' => 'inventory#lookup', 'url' => '/api/lookup/{ean}', 'verb' => 'GET'],
    ['name' => 'inventory#searchName', 'url' => '/api/search', 'verb' => 'POST'],
    ['name' => 'inventory#readLabel', 'url' => '/api/label', 'verb' => 'POST'],
    ['name' => 'inventory#addLocation', 'url' => '/api/locations', 'verb' => 'POST'],
    ['name' => 'inventory#saveProduct', 'url' => '/api/products', 'verb' => 'POST'],
    ['name' => 'inventory#updateProduct', 'url' => '/api/products/{id}', 'verb' => 'POST'],
    ['name' => 'inventory#adjustStock', 'url' => '/api/stock', 'verb' => 'POST'],
    ['name' => 'inventory#upload', 'url' => '/api/products/{id}/files', 'verb' => 'POST'],
    ['name' => 'inventory#download', 'url' => '/api/files/{id}', 'verb' => 'GET'],
    ['name' => 'inventory#export', 'url' => '/api/export/{format}', 'verb' => 'GET'],
]];
