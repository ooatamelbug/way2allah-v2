<?php

namespace App\Support\Permission;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * See Role's docblock — same fix, same reason, for the Permission model.
 */
class Permission extends SpatiePermission
{
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }
}
