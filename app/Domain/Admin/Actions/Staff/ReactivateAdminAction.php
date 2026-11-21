<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Models\AdminAccountState;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminManagementPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * F02 — controlled reactivation. Super-admin only, and a NEW password is
 * required (owner decision 13): no old credential was kept, so none can be
 * restored. Only `password` (bcrypt) is written; `pwd` keeps its marker, so
 * OLD's md5 pass-the-hash paths stay closed, and `API` stays NULL (a new key
 * is issued only on request, outside this action). `uid`, grants and
 * `radminsuper` were never touched by deactivation.
 */
final class ReactivateAdminAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function execute(AdminUser $actor, AdminUser $target, string $newPassword, ?string $ip = null): void
    {
        if (! AdminDeactivation::enabled()) {
            throw new AdminDeactivationRefused('خاصية تعطيل المشرفين غير مفعلة.');
        }
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            throw new AdminDeactivationRefused('لا يمكنك إعادة تفعيل حسابك بنفسك.');
        }

        DB::connection('main')->transaction(function () use ($actor, $target, $newPassword, $ip) {
            $locked = AdminStaffSafety::lockPair((int) $actor->getKey(), (int) $target->getKey());
            if ($locked === null) {
                throw new AdminDeactivationRefused('المشرف غير موجود.');
            }
            if (AdminStaffSafety::isDeactivatedLocked((int) $actor->getKey()) || ! $actor->hasRole('super-admin')) {
                throw new AdminDeactivationRefused('هذا الإجراء متاح للمشرف العام النشط فقط.');
            }
            if (($refusal = AdminManagementPolicy::manageRefusal($actor, $locked)) !== null) {
                throw new AdminDeactivationRefused($refusal);
            }
            if (! AdminStaffSafety::isDeactivatedLocked((int) $locked->getKey())) {
                throw new AdminDeactivationRefused('هذا المشرف ليس معطّلاً.');
            }

            $locked->forceFill(['password' => Hash::make($newPassword)])->save();

            $now = time();
            AdminAccountState::query()->whereKey((int) $locked->getKey())->update([
                'deactivated_at' => null,
                'reactivated_at' => $now,
                'reactivated_by' => (int) $actor->getKey(),
                'updated_at' => $now,
            ]);

            $this->audit->record($actor, 'reactivate_admin', 'authors', 'admin', (int) $locked->getKey(), [
                'target_aid' => (string) $locked->aid,
                'effects' => ['new_password_set' => true, 'api_key_restored' => false],
            ], $ip);
        });
    }
}
