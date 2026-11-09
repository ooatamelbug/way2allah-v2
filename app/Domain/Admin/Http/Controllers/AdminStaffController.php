<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminGrantInspector;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Replaces `admincp/authors/index.php` + `backup/index.php` — Roadmap
 * tasks 5.6/5.7. The two legacy pages are near-duplicates of the same
 * staff-list + add-admin flow (`admincp.md` §5): `authors/index.php`'s
 * add-flow is confirmed dead (`die('hhhh')` before the INSERT);
 * `backup/index.php`'s otherwise-identical copy works, but writes the
 * literal hardcoded password `'way2allah'` to every new admin. Neither is
 * ported as-is — this is one real, consolidated implementation (ADR-0010).
 *
 * Task 5.7's fix: no fixed default password. A random 32-character
 * password is generated and immediately bcrypt-hashed — nobody, including
 * this code, ever holds the plaintext after creation. The new admin's
 * credential is set for real via `PermissionController::updatePassword()`
 * (task 5.3), the one real password-set path in this Wave, not a second
 * one invented here.
 *
 * `backup/index.php`'s own `nuke_backup_booking` JOIN (a second, distinct
 * concept — content-backup coordination, not admin-staff management) is
 * deliberately not surfaced here — Business Confirmation #7 (active need
 * or abandoned?) is still open; the list this controller renders is the
 * plain staff roster only, matching `authors/index.php`'s scope, not
 * `backup/index.php`'s bolted-on extra query.
 */
class AdminStaffController
{
    /** Serialises Laravel admin creation (duplicate checks + insert). */
    public const CREATE_LOCK = 'admin-staff-create';

    public function index(): View
    {
        // `thumb` added — AdminCP Final 12-Route Browser Visual Evidence
        // (2026-08-23): authors/index.php:251's real name column always
        // includes the staff member's `<img class="user-pic">` avatar
        // inline with their name; display-only, not a permission/logic
        // change.
        $staff = AdminUser::on('main')->orderByDesc('radminsuper')->orderBy('aid')->get(['id', 'uid', 'aid', 'email', 'radminsuper', 'thumb']);

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('admin.staff.create');
    }

    /**
     * `backup/index.php:93-140`'s working add-flow, rebuilt: same vBulletin-member-id lookup and already-an-admin guard, no hardcoded password.
     *
     * Admin grant safety (owner-approved 2026-11-08):
     *  - One application lock serialises Laravel admin creation, so two concurrent requests cannot both pass the
     *    duplicate checks. Gen 2 / OLD AdminCP creation does not take this lock.
     *  - Duplicate usernames are refused: the `aid` comparison runs in the database, so the column's collation
     *    decides case / accent / trailing-space equality. The forum-uid check is unchanged.
     *  - The row is inserted inside a `main` transaction; before commit, the Spatie grant tables are checked for
     *    grants already stored under the newly allocated `nuke_authors.id` (an id reused after a MySQL 5.7
     *    restart would inherit a deleted admin's grants). Any found → rollback and a controlled refusal; the
     *    grants are never deleted or reassigned. If the check itself cannot run, the creation is rolled back too
     *    (fail closed). The log carries only the id and counts.
     */
    public function store(Request $request, AdminGrantInspector $grants): RedirectResponse
    {
        $vbUserId = (int) $request->input('vbuid');

        $vbUser = DB::connection('vbulletin')->table('user')->where('userid', $vbUserId)->first(['userid', 'username', 'email']);

        if ($vbUser === null) {
            return back()->with('error', 'هذا العضو غير موجود بالمنتدى');
        }

        $lock = Cache::lock(self::CREATE_LOCK, 30);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            return back()->with('error', 'عملية إضافة مشرف أخرى قيد التنفيذ، من فضلك حاول مرة أخرى بعد قليل');
        }

        try {
            if (AdminUser::on('main')->where('uid', $vbUser->userid)->exists()) {
                return back()->with('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');
            }

            if (AdminUser::on('main')->where('aid', $vbUser->username)->exists()) {
                return back()->with('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
            }

            $main = DB::connection('main');
            $main->beginTransaction();

            try {
                $admin = AdminUser::on('main')->create([
                    'uid' => $vbUser->userid,
                    'aid' => $vbUser->username,
                    'name' => $vbUser->username,
                    'email' => $vbUser->email,
                    'password' => Hash::make(Str::random(32)),
                    'radminsuper' => 0,
                ]);

                $existing = $grants->grantCountsFor((int) $admin->getKey());
            } catch (Throwable $e) {
                $main->rollBack();
                Log::error('admin-create: refused — creation or grant check failed', ['exception' => $e::class]);

                return back()->with('error', 'تعذر إضافة المشرف بأمان، لم يتم حفظ أي تغيير. من فضلك حاول مرة أخرى أو راجع سجل الأخطاء');
            }

            if ($existing['roles'] + $existing['permissions'] > 0) {
                $main->rollBack();
                Log::warning('admin-create: refused — grants already exist under the new admin id', [
                    'admin_id' => (int) $admin->getKey(),
                    'roles' => $existing['roles'],
                    'permissions' => $existing['permissions'],
                ]);

                return back()->with('error', 'لم يتم إضافة المشرف: توجد صلاحيات سابقة مسجلة على نفس رقم المشرف. شغّل php artisan admin:audit-grants وراجع النتائج قبل المحاولة مرة أخرى');
            }

            $main->commit();
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم إضافة العضو كمشرف بالموقع، يمكنك الآن تعيين صلاحياته وكلمة مروره');
    }
}
