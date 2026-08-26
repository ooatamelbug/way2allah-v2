<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /** `backup/index.php:93-140`'s working add-flow, rebuilt: same vBulletin-member-id lookup and already-an-admin guard, no hardcoded password. */
    public function store(Request $request): RedirectResponse
    {
        $vbUserId = (int) $request->input('vbuid');

        $vbUser = DB::connection('vbulletin')->table('user')->where('userid', $vbUserId)->first(['userid', 'username', 'email']);

        if ($vbUser === null) {
            return back()->with('error', 'هذا العضو غير موجود بالمنتدى');
        }

        if (AdminUser::on('main')->where('uid', $vbUser->userid)->exists()) {
            return back()->with('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');
        }

        $admin = AdminUser::on('main')->create([
            'uid' => $vbUser->userid,
            'aid' => $vbUser->username,
            'name' => $vbUser->username,
            'email' => $vbUser->email,
            'password' => Hash::make(Str::random(32)),
            'radminsuper' => 0,
        ]);

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم إضافة العضو كمشرف بالموقع، يمكنك الآن تعيين صلاحياته وكلمة مروره');
    }
}
