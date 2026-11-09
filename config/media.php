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

    /*
    |--------------------------------------------------------------------------
    | Search autocomplete lists
    |--------------------------------------------------------------------------
    |
    | The directory holding `authors.txt` / `channels.txt` / `masjids.txt`,
    | which the header's search reads and which «تعديل بيانات الداعية» /
    | «حذف الداعية» regenerate (OLD's update_ajax_files_with_data()). The
    | default is this application's own public directory; a deployment can
    | point it elsewhere without code changes.
    |
    */
    'autocomplete_root' => env('AUTOCOMPLETE_ROOT', public_path('w2a_autocomplete')),
];
