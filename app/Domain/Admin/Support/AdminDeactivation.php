<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminAccountState;

/**
 * F02 — the single read of administrator deactivation state for Laravel.
 *
 * While `admin_deactivation.enabled` is off (the default) this answers
 * "not deactivated" without touching the database, so the feature has zero
 * cost and zero risk before its migration exists. When on, it is one primary
 * key lookup on `main` per call, uncached (owner decision 12: no cache).
 *
 * Scope: Laravel only. Gen 2, OLD and the bridge do not read this table yet
 * (legacy patches G1–G3 / O1–O4, B3 design review §13.5).
 */
final class AdminDeactivation
{
    public static function enabled(): bool
    {
        return (bool) config('admin_deactivation.enabled', false);
    }

    public static function isDeactivated(int $adminId): bool
    {
        if (! self::enabled() || $adminId < 1) {
            return false;
        }

        return AdminAccountState::query()
            ->whereKey($adminId)
            ->whereNotNull('deactivated_at')
            ->exists();
    }

    /** @return list<int> ids currently deactivated (empty while the feature is off) */
    public static function deactivatedIds(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return AdminAccountState::query()->whereNotNull('deactivated_at')->pluck('admin_id')->map(fn ($id) => (int) $id)->all();
    }
}
