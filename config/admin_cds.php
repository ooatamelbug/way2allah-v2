<?php

/*
|--------------------------------------------------------------------------
| OLD Admin Migration Phase 6 — CD / Album Downloads administration
|--------------------------------------------------------------------------
|
| `image_root` is where release images are written. It defaults to the
| directory the public CD pages read (`/images/cds_image2/<file>`, served
| from public_path() — CPANEL-NO-SSH-DEPLOYMENT.md §6). It is configurable
| only so tests and local verification can write somewhere isolated:
| locally, public/images is a symlink into the protected legacy tree.
|
| `media_root` is where a release's banner is written — `w2acd/banner/
| {floor(id/1000)}/{id}.gif` under it, the file OLD's `UploadBanner` wrote and
| `cds-code-{id}-{type}.htm` reads (W2ACD public admin parity, Phase 1). Same
| contract as `admin_khotab.media_root`.
|
*/

return [
    'image_root' => env('W2ACD_IMAGE_ROOT', public_path('images/cds_image2')),
    'media_root' => env('W2ACD_MEDIA_ROOT', public_path('media')),
];
