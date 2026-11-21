<?php

namespace App\Domain\Admin\Actions\Staff;

use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminManagementPolicy;
use App\Domain\Admin\Support\AdminOwners;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rename an administrator's login name (`nuke_authors.aid`) — OLD `mod_authors` → `UpdateAuthor` parity.
 *
 * What changes, as in OLD (`authors.php:425-545`): `nuke_authors.aid`, and the news stories attributed to the old
 * login name (`nuke_stories.aid`, and `informant` where it equalled the lower-cased old aid). Nothing else: the row id,
 * forum `uid`, `name`, email, passwords, `radminsuper`, Spatie roles / permissions (keyed by id), and every audit row
 * (Laravel's `admin_audit_log` keys the actor by id; legacy `nuke_islamic_logs` rows keep the name they were written
 * under, as in OLD) are untouched.
 *
 * Rules: owner actor only (only owners could open OLD's screen); target not deactivated; an account whose ownership
 * comes from its login name is never renamed (it would silently lose — or another account gain — OLD authority); the
 * new name must not be an owner login name or `Good`, must be unique among admins case- and trailing-space-
 * insensitively (OLD's `utf8_unicode_ci` lookup), and must not be another forum member's username. Serialised with
 * admin creation (same uniqueness domain). Audited as `rename_admin` (old → new, stories re-attributed).
 *
 * Disabled unless `admin_rename.enabled` (see config/admin_rename.php for why).
 */
final class RenameAdminAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function execute(AdminUser $actor, AdminUser $target, string $newAid, ?string $ip = null): void
    {
        if (! (bool) config('admin_rename.enabled', false)) {
            throw new AdminManagementRefused('تعديل اسم الدخول غير مفعل بعد (يُفعّل بعد إيقاف النظام القديم).');
        }
        $newAid = trim($newAid);
        if (($refusal = AdminManagementPolicy::renameRefusal($actor, $target)) !== null) {
            throw new AdminManagementRefused($refusal);
        }
        if (AdminDeactivation::isDeactivated((int) $target->getKey())) {
            throw new AdminManagementRefused('هذا المشرف معطّل؛ أعد تفعيله أولاً.');
        }
        if ($newAid === '' || mb_strlen($newAid) > 25 || preg_match('/[<>\x00-\x1F]/', $newAid) === 1) {
            throw new AdminManagementRefused('اسم الدخول غير صالح (حتى 25 حرفًا بدون رموز خاصة).');
        }
        if (AdminOwners::isReservedIdentity($newAid, '')) {
            throw new AdminManagementRefused('هذه الكنية أو هذا الإسم محجوز لحسابات الملاك؛ اختر غيره.');
        }
        if ((string) $target->aid === $newAid) {
            throw new AdminManagementRefused('اسم الدخول الجديد مطابق للحالي.');
        }

        $lock = Cache::lock(AdminStaffController::CREATE_LOCK, 30);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new AdminManagementRefused('عملية أخرى على المشرفين قيد التنفيذ، حاول مرة أخرى بعد قليل.');
        }

        try {
            $fold = static fn (string $v): string => mb_strtolower(rtrim($v, ' '), 'UTF-8');
            $clash = AdminUser::on('main')->whereKeyNot($target->getKey())->get(['id', 'aid'])
                ->contains(fn (AdminUser $a) => $fold((string) $a->aid) === $fold($newAid));
            if ($clash) {
                throw new AdminManagementRefused('يوجد مشرف آخر بنفس اسم الدخول.');
            }
            $uid = (int) $target->uid;
            if (DB::connection('vbulletin')->table('user')->where('username', $newAid)->where('userid', '!=', $uid)->exists()) {
                throw new AdminManagementRefused('هذه الكنية اسم مستخدم لعضو آخر بالمنتدى؛ اختر كنية أخرى.');
            }

            DB::connection('main')->transaction(function () use ($actor, $target, $newAid, $ip) {
                /** @var AdminUser $locked */
                $locked = AdminUser::on('main')->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
                $oldAid = (string) $locked->aid;

                // OLD: informant follows when it equalled the lower-cased old aid; `informant` is varchar(20).
                $stories = DB::connection('main')->table('nuke_stories')->where('aid', $oldAid)->get(['sid', 'aid', 'informant']);
                $informantRows = $stories->filter(fn ($s) => strtolower((string) $s->aid) === (string) $s->informant)->pluck('sid')->all();
                if ($informantRows !== [] && mb_strlen($newAid) > 20) {
                    throw new AdminManagementRefused('لهذا المشرف أخبار مسجلة باسمه، وحقل الناشر فيها لا يتسع لأكثر من 20 حرفًا؛ اختر اسمًا أقصر.');
                }

                $locked->forceFill(['aid' => $newAid])->save();
                if ($informantRows !== []) {
                    DB::connection('main')->table('nuke_stories')->whereIn('sid', $informantRows)->update(['informant' => $newAid]);
                }
                $storiesUpdated = $stories->isEmpty() ? 0 : DB::connection('main')->table('nuke_stories')->whereIn('sid', $stories->pluck('sid')->all())->update(['aid' => $newAid]);

                $this->audit->record($actor, 'rename_admin', 'authors', 'admin', (int) $locked->getKey(), [
                    'old_aid' => $oldAid,
                    'new_aid' => $newAid,
                    'stories_reattributed' => $storiesUpdated,
                ], $ip);
            });
        } finally {
            $lock->release();
        }
    }
}
