<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator-management authority — OLD `admin.php?op=mod_authors` rules
    |--------------------------------------------------------------------------
    |
    | Taken verbatim from OLD `admin/modules/authors.php`; no account id is
    | configured or guessed. They are evaluated against the live `nuke_authors`
    | rows (AdminOwners).
    |
    | Owners (OLD line 21, the gate of the whole screen): `radminsuper = 1` AND
    | (`name` == 'Good' OR login name == 'Admin' OR == 'مؤمن يحب الله ورسوله').
    | OLD compared the login name as typed / as the forum username, after MySQL
    | had matched it under the column's case-insensitive collation, so the aid
    | test is case-insensitive here too (e.g. stored `admin` qualifies).
    |
    | Protected main account (OLD lines 48/57/238/436/721): `name` == 'Good'
    | (exact) — no delete, no super-user downgrade, no module checkboxes.
    |
    | If no row satisfies the owner rule, every owner-only operation stays blocked.
    |
    */

    'owner_names' => ['Good'],

    'owner_aids' => ['Admin', 'مؤمن يحب الله ورسوله'],

    'protected_names' => ['Good'],

];
