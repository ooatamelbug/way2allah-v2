<?php

namespace App\Support\Permission;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's Role/Permission tables are new, Laravel-owned tables on the
 * application's default connection (ADR-0011) — never on 'main',
 * 'vbulletin', or 'flashchat'. Without this override, Eloquent's
 * newRelatedInstance() silently inherits whichever connection the
 * *querying* domain model uses (e.g. VbUser's 'vbulletin' connection) onto
 * this model too, since Spatie's base class leaves $connection unset. That
 * would point role/permission lookups at the wrong database entirely, or
 * fail outright when a legacy connection has no `roles` table (as it must
 * not). Explicitly pinning the connection here prevents that regardless of
 * which domain model's relationship resolves it.
 */
class Role extends SpatieRole
{
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }
}
