<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Actions\Staff\AdminStaffSafety;
use App\Domain\Admin\Models\AdminUser;

/**
 * Administrator management (OLD `admin.php?op=mod_authors` parity): who may change whom.
 *
 * - **Laravel super-admin** = the Spatie `super-admin` role (the role Laravel authorises with).
 * - **Owner** = OLD's mod_authors gate (AdminOwners: radminsuper 1 + name `Good` / login `Admin` /
 *   `مؤمن يحب الله ورسوله`). **Protected main account** = name `Good` (AdminOwners).
 *
 * Rules (each returns null when allowed, else the refusal message):
 * - managing an account (profile, password, permissions, deactivate / reactivate): the actor is a Laravel super-admin;
 *   an owner, the main account or **another super-admin** can be managed only by an owner (in OLD only owners could
 *   open the screen at all); a regular admin by any super-admin (Gen 2's `authors.*` model, F02).
 * - changing the super-admin role (promote / demote): owners only (OLD), never on oneself, never on the main account
 *   (OLD never downgraded `Good`). As in OLD, an owner may change another owner.
 *
 * If no account satisfies OLD's owner rule, everything that needs an owner is refused (fail closed).
 */
final class AdminManagementPolicy
{
    public static function isLaravelSuperAdmin(AdminUser $admin): bool
    {
        return $admin->hasRole('super-admin');
    }

    /** Either definition: Laravel's role or the legacy `radminsuper` flag. */
    public static function isAnySuperAdmin(AdminUser $admin): bool
    {
        return AdminStaffSafety::isSuperAdmin($admin);
    }

    public static function isOwner(AdminUser $admin): bool
    {
        return AdminOwners::isOwner($admin);
    }

    public static function isProtectedMainAccount(AdminUser $admin): bool
    {
        return AdminOwners::isProtectedMainAccount($admin);
    }

    public static function manageRefusal(AdminUser $actor, AdminUser $target): ?string
    {
        if (! self::isLaravelSuperAdmin($actor)) {
            return 'هذا الإجراء متاح للمشرف العام فقط.';
        }
        $self = (int) $actor->getKey() === (int) $target->getKey();
        if ((self::isOwner($target) || self::isProtectedMainAccount($target)) && ! self::isOwner($actor)) {
            return 'هذا حساب مالك محمي؛ لا يعدّله إلا حساب مالك.';
        }
        if (! $self && self::isAnySuperAdmin($target) && ! self::isOwner($actor)) {
            return 'إدارة حساب مشرف عام آخر متاحة لحسابات الملاك فقط.';
        }

        return null;
    }

    public static function roleChangeRefusal(AdminUser $actor, AdminUser $target): ?string
    {
        if (! self::isOwner($actor) || ! self::isLaravelSuperAdmin($actor)) {
            return 'ترقية المشرفين وتنزيلهم متاحة لحسابات الملاك فقط.';
        }
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            return 'لا يمكنك تغيير صلاحيتك بنفسك.';
        }
        if (self::isProtectedMainAccount($target)) {
            return 'لا يمكن تغيير صلاحية الحساب الرئيسي.';
        }

        return null;
    }

    /**
     * Login-name rename (OLD UpdateAuthor `aid`): owners only, as only owners could open OLD's screen. An account whose
     * ownership comes from its login name is never renamed (it would lose OLD authority without a role change).
     */
    public static function renameRefusal(AdminUser $actor, AdminUser $target): ?string
    {
        if (! self::isOwner($actor) || ! self::isLaravelSuperAdmin($actor)) {
            return 'تعديل اسم الدخول متاح لحسابات الملاك فقط.';
        }
        if (self::isOwner($target) && ! in_array((string) $target->name, (array) config('admin_owners.owner_names', []), true)) {
            return 'لا يمكن تعديل اسم الدخول لحساب مالك؛ صلاحيته مرتبطة بهذا الاسم.';
        }

        return null;
    }

    /** May this actor grant super-admin while creating an admin? */
    public static function mayGrantSuperAdminOnCreate(AdminUser $actor): bool
    {
        return self::isOwner($actor) && self::isLaravelSuperAdmin($actor);
    }
}
