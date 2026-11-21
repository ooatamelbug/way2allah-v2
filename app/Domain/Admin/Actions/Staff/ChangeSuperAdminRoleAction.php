<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminManagementPolicy;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Promote / demote a super-admin (OLD `mod_authors` "Super User" checkbox). Owners only (AdminManagementPolicy).
 *
 * Writes **Laravel's** `super-admin` role only (Spatie). The legacy `nuke_authors.radminsuper` column, which Gen 2 and
 * OLD read, is deliberately NOT changed: writing it would change privileges in the legacy systems, which is an owner
 * decision (reported), not something this action assumes.
 *
 * Serialised by one application lock so two owners cannot race the "last active super-admin" rule. Audited
 * (`promote_super_admin` / `demote_super_admin`); if the audit write fails the role change is reverted.
 */
final class ChangeSuperAdminRoleAction
{
    public const LOCK = 'admin-role-change';

    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function promote(AdminUser $actor, AdminUser $target, ?string $ip = null): void
    {
        $this->change($actor, $target, true, $ip);
    }

    public function demote(AdminUser $actor, AdminUser $target, ?string $ip = null): void
    {
        $this->change($actor, $target, false, $ip);
    }

    private function change(AdminUser $actor, AdminUser $target, bool $promote, ?string $ip): void
    {
        if (($refusal = AdminManagementPolicy::roleChangeRefusal($actor, $target)) !== null) {
            throw new AdminManagementRefused($refusal);
        }
        if (AdminDeactivation::isDeactivated((int) $target->getKey())) {
            throw new AdminManagementRefused('هذا المشرف معطّل؛ أعد تفعيله أولاً.');
        }

        $lock = Cache::lock(self::LOCK, 15);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new AdminManagementRefused('عملية تغيير صلاحيات أخرى قيد التنفيذ، حاول مرة أخرى بعد قليل.');
        }

        try {
            $has = $target->hasRole('super-admin');
            if ($promote && $has) {
                throw new AdminManagementRefused('هذا المشرف مشرف عام بالفعل.');
            }
            if (! $promote && ! $has) {
                throw new AdminManagementRefused('هذا المشرف ليس مشرفًا عامًا.');
            }
            if (! $promote) {
                $deactivated = AdminDeactivation::deactivatedIds();
                $remaining = array_diff(AdminStaffSafety::spatieSuperAdminIds(), $deactivated, [(int) $target->getKey()]);
                if (count($remaining) < 1) {
                    throw new AdminManagementRefused('لا يمكن تنزيل آخر مشرف عام نشط.');
                }
            }

            $promote ? $target->assignRole('super-admin') : $target->removeRole('super-admin');

            try {
                $this->audit->record($actor, $promote ? 'promote_super_admin' : 'demote_super_admin', 'authors', 'admin', (int) $target->getKey(), [
                    'target_aid' => (string) $target->aid,
                    'laravel_role_only' => true,
                    'legacy_radminsuper_unchanged' => (int) $target->radminsuper,
                ], $ip);
            } catch (Throwable $e) {
                $promote ? $target->removeRole('super-admin') : $target->assignRole('super-admin');
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }
}
