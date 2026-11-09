<?php

/*
|--------------------------------------------------------------------------
| Anasheed public-page administration (OLD public admin parity)
|--------------------------------------------------------------------------
|
| `media_root` is where «إضافة صورة للقسم» writes a group icon, the file OLD's
| UploadIcon() wrote to `$mediaBase/media/anasheed/icons/{floor(id/1000)}/{id}.jpg`.
| It is the directory Laravel's public pages read (`public_path('media')`,
| see MediaPathResolver), and is configurable so tests and verification stay
| isolated — the same contract as `admin_khotab.media_root`.
|
| `icon_max_bytes` is OLD's effective icon limit: the upload form posted
| `MAX_FILE_SIZE=10000` (PHP refuses a larger file before the handler runs)
| and told the admin «أقصى حجم مسموح به 10 كيلو بايت».
|
*/

return [
    'media_root' => env('ANASHEED_MEDIA_ROOT', public_path('media')),
    'icon_max_bytes' => (int) env('ANASHEED_ICON_MAX_BYTES', 10000),
    // «اضافة صورة للمادة» (item page): OLD UploadBanner()'s server limit, MAXFSIZE
    // = 40 000 bytes (`d_config.php`; the form's MAX_FILE_SIZE was the looser 150 000).
    'frame_max_bytes' => (int) env('ANASHEED_FRAME_MAX_BYTES', 40000),
];
