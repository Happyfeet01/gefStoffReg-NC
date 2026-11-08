<?php
declare(strict_types=1);

return ['routes' => [
    ['name' => 'inventory#saveApiKey', 'url' => '/api/settings/key', 'verb' => 'POST'],
    ['name' => 'inventory#index', 'url' => '/', 'verb' => 'GET'],
    ['name' => 'inventory#bootstrap', 'url' => '/api/bootstrap', 'verb' => 'GET'],
    ['name' => 'inventory#shares', 'url' => '/api/shares', 'verb' => 'GET'],
    ['name' => 'inventory#saveShare', 'url' => '/api/shares', 'verb' => 'POST'],
    ['name' => 'inventory#revokeShare', 'url' => '/api/shares/revoke', 'verb' => 'POST'],
    // Logged-in managers: link administration (POST requests retain normal CSRF checking).
    ['name' => 'external_share#listLinks', 'url' => '/api/external-links', 'verb' => 'GET'],
    ['name' => 'external_share#createLink', 'url' => '/api/external-links', 'verb' => 'POST'],
    ['name' => 'external_share#revokeLink', 'url' => '/api/external-links/revoke', 'verb' => 'POST'],
    // Public token view; data and PDF requests are POST-only and recheck password, scope and expiry.
    ['name' => 'external_share#view', 'url' => '/shared/{token}', 'verb' => 'GET'],
    ['name' => 'external_share#read', 'url' => '/shared/{token}/data', 'verb' => 'POST'],
    ['name' => 'external_share#stock', 'url' => '/shared/{token}/stock', 'verb' => 'POST'],
    ['name' => 'external_share#file', 'url' => '/shared/{token}/file/{id}', 'verb' => 'POST'],
    ['name' => 'inventory#lookup', 'url' => '/api/lookup/{ean}', 'verb' => 'GET'],
    ['name' => 'inventory#previewSource', 'url' => '/api/source-preview', 'verb' => 'POST'],
    ['name' => 'inventory#researchProduct', 'url' => '/api/research', 'verb' => 'POST'],
    ['name' => 'inventory#readLabel', 'url' => '/api/label', 'verb' => 'POST'],
    ['name' => 'inventory#analyzeSds', 'url' => '/api/sds-analysis', 'verb' => 'POST'],
    ['name' => 'inventory#previewSdsDraft', 'url' => '/api/sds-preview/{token}', 'verb' => 'GET'],
    ['name' => 'inventory#addLocation', 'url' => '/api/locations', 'verb' => 'POST'],
    ['name' => 'inventory#saveProduct', 'url' => '/api/products', 'verb' => 'POST'],
    ['name' => 'inventory#updateProduct', 'url' => '/api/products/{id}', 'verb' => 'POST'],
    ['name' => 'inventory#adjustStock', 'url' => '/api/stock', 'verb' => 'POST'],
    ['name' => 'inventory#upload', 'url' => '/api/products/{id}/files', 'verb' => 'POST'],
    ['name' => 'inventory#download', 'url' => '/api/files/{id}', 'verb' => 'GET'],
    ['name' => 'inventory#export', 'url' => '/api/export/{format}', 'verb' => 'GET'],
]];
