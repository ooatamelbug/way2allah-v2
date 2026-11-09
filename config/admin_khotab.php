<?php

/*
|--------------------------------------------------------------------------
| Khotab public-page administration (OLD public admin parity)
|--------------------------------------------------------------------------
|
| `media_root` is where "إضافة مادة" writes the archive.org preview images
| that OLD's ConfirmInsertKhotab downloaded into `$ServerBase/media/…`:
| `khotab_gifs/{floor(id/1000)}/{id}.gif` and
| `khotab_frames/{floor(id/1000)}/{id}.jpg`. It is the directory Laravel's
| public pages read (`public_path('media')`, see MediaPathResolver), and is
| configurable so tests and verification stay isolated — the same contract
| as `admin_cds.image_root`.
|
| `archive_timeout` bounds each archive.org request (metadata and image),
| which OLD made with no timeout at all.
|
*/

return [
    'media_root' => env('KHOTAB_MEDIA_ROOT', public_path('media')),
    'archive_timeout' => (int) env('KHOTAB_ARCHIVE_TIMEOUT', 20),
];
