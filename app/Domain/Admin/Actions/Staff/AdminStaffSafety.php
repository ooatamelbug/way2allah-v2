<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Models\AdminAccountState;
use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Illuminate\Support\Facades\DB;

/**
 * F02 — shared rules for deactivate / reactivate.
 *
 * "Super-admin" here is either authority the systems use: Laravel's Spatie
 * `super-admin` role, or the legacy `nuke_authors.radminsuper = 1` that Gen 2
 * and OLD use (plan F02.2 rule 5). Spatie's tables are on the default
 * connection, so they are read through it rather than joined to `main`.
 */
final class AdminStaffSafety
{
    public static function isSuperAdmin(AdminUser $admin): bool
    {
        return (bool) $admin->radminsuper || in_array((int) $admin->getKey(), self::spatieSuperAdminIds(), true);
    }

    /**
     * Super-admins (either definition) that are not deactivated, minus $excludeId.
     * Must be called inside the `main` transaction that holds the row locks.
     */
    public static function remainingActiveSuperAdmins(int $excludeId): int
    {
        $legacy = AdminUser::on('main')->where('radminsuper', 1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = array_unique(array_merge($legacy, self::spatieSuperAdminIds()));

        $deactivated = AdminAccountState::query()->whereNotNull('deactivated_at')->pluck('admin_id')->map(fn ($id) => (int) $id)->all();
        $existing = AdminUser::on('main')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return count(array_diff($existing, $deactivated, [$excludeId]));
    }

    /**
     * Lock both admin rows in id order (no deadlock between two crossing requests), then return the target.
     * Mutual deactivation therefore serialises: the second request re-checks its actor after the first commits.
     */
    public static function lockPair(int $actorId, int $targetId): ?AdminUser
    {
        $ids = array_values(array_unique([$actorId, $targetId]));
        sort($ids);

        $rows = AdminUser::on('main')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // Also lock any existing state rows of the pair.
        AdminAccountState::query()->whereIn('admin_id', $ids)->orderBy('admin_id')->lockForUpdate()->get();

        return $rows->get($targetId);
    }

    public static function isDeactivatedLocked(int $adminId): bool
    {
        return AdminAccountState::query()->whereKey($adminId)->whereNotNull('deactivated_at')->exists();
    }

    /** @return list<int> ids holding Laravel's `super-admin` role (Spatie, default connection) */
    public static function spatieSuperAdminIds(): array
    {
        $role = Role::query()->where('name', 'super-admin')->where('guard_name', 'admin')->first();
        if ($role === null) {
            return [];
        }

        return DB::connection($role->getConnectionName())
            ->table((string) config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', (new AdminUser)->getMorphClass())
            ->pluck((string) config('permission.column_names.model_morph_key', 'model_id'))
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
