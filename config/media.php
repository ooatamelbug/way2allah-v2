<?php

$assetBaseUrl = rtrim((string) env('ASSET_BASE_URL', ''), '/');

return [
    /*
    |--------------------------------------------------------------------------
    | Public media URLs
    |--------------------------------------------------------------------------
    |
    | MEDIA_BASE_URL points at the public `media` directory. The thumbnail
    | URL points at the resize endpoint itself. Both can use a different host
    | (for example, a media server or CDN) without changing application code.
    |
    */
    'base_url' => rtrim((string) env(
        'MEDIA_BASE_URL',
        $assetBaseUrl !== '' ? $assetBaseUrl.'/media' : '/media'
    ), '/'),

    'thumbnail_url' => (string) env(
        'THUMBNAIL_BASE_URL',
        $assetBaseUrl !== '' ? $assetBaseUrl.'/thumbnails.php' : '/thumbnails.php'
    ),
];
