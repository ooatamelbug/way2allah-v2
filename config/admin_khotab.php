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

    // F10 / B5: how long a custom-cover upload or removal waits for another one on the same item (seconds).
    'cover_lock_wait' => (int) env('KHOTAB_COVER_LOCK_WAIT', 10),

    // Multi-author creation (owner decisions 2026-11-25): «إضافة مادة» may also create independent copies of the item
    // for other sheikhs. The one place these limits live — the request rules, the action, the form and the author
    // search all read them from here.
    'additional_authors' => [
        'max' => 10,                 // additional sheikhs per submission
        'search_page_size' => 20,    // results per author-search request
        'search_min_length' => 2,    // characters (after normalization) before the search runs
    ],
];
