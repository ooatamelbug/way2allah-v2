<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Models\AdminAccountState;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminManagementPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F02 — deactivate an administrator (owner decisions 2026-11-14; B3 design
 * review §13.3–§13.6). The account is never deleted: `uid`, `aid`, `name`,
 * `email`, `radminsuper`, the legacy `permissions` blob and the Spatie grants
 * all stay, so history, attribution and a later reactivation are intact.
 *
 * In one `main` transaction, after locking the actor's and target's rows:
 *  1. rules: super-admin actor that is itself still active; no self;
 *     target not already deactivated; at least one other active super-admin
 *     must remain;
 *  2. `admin_account_states` row → deactivated (who, when, reason);
 *  3. credentials neutralised on `nuke_authors`: `password` becomes a
 *     non-hash random marker and `pwd` a non-hex random marker, `API` NULL.
 *     That blocks every password login that reads these columns (Laravel,
 *     Gen 2 incl. its plain-text branch; with P0-A deployed it also ends open
 *     Gen 2 AdminCP sessions) and all three backup-API endpoints. The markers
 *     are never shown, logged or audited;
 *  4. audit `deactivate_admin`.
 *
 * Laravel access is revoked by `AdminGuard` on the target's next request.
 * NOT covered (needs legacy patches, B3 §13.5): OLD bridge cookies and OLD
 * super-admin sessions, Gen 2 forum-session admin hooks, and OLD's md5
 * paths once `vb5.php` rewrites `pwd` on a forum login.
 */
final class DeactivateAdminAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function execute(AdminUser $actor, AdminUser $target, string $reason, ?string $ip = null): void
    {
        if (! AdminDeactivation::enabled()) {
            throw new AdminDeactivationRefused('خاصية تعطيل المشرفين غير مفعلة.');
        }
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            throw new AdminDeactivationRefused('لا يمكنك تعطيل حسابك.');
        }

        DB::connection('main')->transaction(function () use ($actor, $target, $reason, $ip) {
            $locked = AdminStaffSafety::lockPair((int) $actor->getKey(), (int) $target->getKey());
            if ($locked === null) {
                throw new AdminDeactivationRefused('المشرف غير موجود.');
            }
            if (AdminStaffSafety::isDeactivatedLocked((int) $actor->getKey()) || ! $actor->hasRole('super-admin')) {
                throw new AdminDeactivationRefused('هذا الإجراء متاح للمشرف العام النشط فقط.');
            }
            // Administrator management (OLD mod_authors parity): the main account (`Good`) is never removed, as OLD's
            // delete excluded it; an owner or another super-admin only by an owner (AdminManagementPolicy).
            if (AdminManagementPolicy::isProtectedMainAccount($locked)) {
                throw new AdminDeactivationRefused('هذا هو الحساب الرئيسي المحمي ولا يمكن تعطيله.');
            }
            if (($refusal = AdminManagementPolicy::manageRefusal($actor, $locked)) !== null) {
                throw new AdminDeactivationRefused($refusal);
            }
            if (AdminStaffSafety::isDeactivatedLocked((int) $locked->getKey())) {
                throw new AdminDeactivationRefused('هذا المشرف معطّل بالفعل.');
            }
            if (AdminStaffSafety::isSuperAdmin($locked) && AdminStaffSafety::remainingActiveSuperAdmins((int) $locked->getKey()) < 1) {
                throw new AdminDeactivationRefused('لا يمكن تعطيل آخر مشرف عام نشط.');
            }

            $hadApiKey = (string) $locked->getAttribute('API') !== '';

            $locked->forceFill([
                'password' => '!disabled:'.Str::random(48),
                'pwd' => 'x'.Str::random(39),
                'API' => null,
            ])->save();

            $now = time();
            AdminAccountState::query()->updateOrCreate(
                ['admin_id' => (int) $locked->getKey()],
                ['deactivated_at' => $now, 'deactivated_by' => (int) $actor->getKey(), 'reason' => $reason, 'updated_at' => $now],
            );

            $this->audit->record($actor, 'deactivate_admin', 'authors', 'admin', (int) $locked->getKey(), [
                'target_aid' => (string) $locked->aid,
                'reason' => $reason,
                'effects' => [
                    'laravel_access_revoked' => true,
                    'credentials_neutralised' => true,
                    'api_key_cleared' => $hadApiKey,
                    'uid_preserved' => true,
                    'legacy_bridge_and_forum_hooks_unaffected' => true,
                ],
            ], $ip);
        });
    }
}
