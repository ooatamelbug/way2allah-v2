<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;

/**
 * OLD `mod_authors` authority, evaluated on the live `nuke_authors` row (`config/admin_owners.php`).
 *
 * - **Owner** (may manage administrators, grant / revoke super user): `radminsuper = 1` AND (name `Good` OR login name
 *   `Admin` / `مؤمن يحب الله ورسوله`, compared case-insensitively as OLD's collation-matched login did).
 * - **Protected main account**: name exactly `Good` — never deactivated, never demoted.
 *
 * No ids are configured: if production has no row matching the rule, owner-only operations stay blocked.
 */
final class AdminOwners
{
    public static function isOwner(AdminUser $admin): bool
    {
        if ((int) $admin->radminsuper !== 1) {
            return false;
        }
        if (in_array((string) $admin->name, self::list('owner_names'), true)) {
            return true;
        }
        $aid = self::fold((string) $admin->aid);
        foreach (self::list('owner_aids') as $ownerAid) {
            if ($aid !== '' && $aid === self::fold($ownerAid)) {
                return true;
            }
        }

        return false;
    }

    public static function isProtectedMainAccount(AdminUser $admin): bool
    {
        return in_array((string) $admin->name, self::list('protected_names'), true);
    }

    /** Would this nickname / name collide with an OLD authority identity? (refused at creation) */
    public static function isReservedIdentity(string $aid, string $name): bool
    {
        $folded = self::fold($aid);
        foreach (self::list('owner_aids') as $ownerAid) {
            if ($folded === self::fold($ownerAid)) {
                return true;
            }
        }
        foreach (array_merge(self::list('owner_names'), self::list('protected_names')) as $reserved) {
            if (self::fold($name) === self::fold($reserved) || $folded === self::fold($reserved)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> ids of the rows that satisfy the owner rule */
    public static function ownerIds(): array
    {
        return AdminUser::on('main')->where('radminsuper', 1)->get(['id', 'aid', 'name', 'radminsuper'])
            ->filter(fn (AdminUser $a) => self::isOwner($a))->map(fn (AdminUser $a) => (int) $a->id)->values()->all();
    }

    /** utf8_unicode_ci-like equality for the login name: case-insensitive, trailing spaces ignored. */
    private static function fold(string $value): string
    {
        return mb_strtolower(rtrim($value, ' '), 'UTF-8');
    }

    /** @return list<string> */
    private static function list(string $key): array
    {
        return array_values(array_map('strval', (array) config('admin_owners.'.$key, [])));
    }
}
