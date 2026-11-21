<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator login-name rename (OLD `mod_authors` → UpdateAuthor `aid`)
    |--------------------------------------------------------------------------
    |
    | OFF by default. While OLD still runs, a rename breaks that administrator
    | in OLD (verified from OLD's source, 2026-11-17):
    |  - OLD's module access compares `nuke_modules.admins` (names) with the
    |    LOGIN NAME (`way2allah_functions.php` is_admin checks);
    |  - OLD's forum bridge logs in with aid = forum username (`vb5.php:121`);
    |  - OLD's fine-grained grants `nuke_islamic_mod` / `nuke_anasheed_mod` /
    |    `nuke_telawah_mod` / `nuke_cat_mod` / … are keyed by the login name.
    | OLD's own rename had the same effect (it only re-attributed
    | `nuke_stories`). Enable only after OLD is retired; Laravel and Gen 2 log in
    | by `aid` + password and keep working.
    |
    */

    'enabled' => (bool) env('ADMIN_RENAME_ENABLED', false),

];
