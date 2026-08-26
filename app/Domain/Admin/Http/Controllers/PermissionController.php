<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Replaces `admincp/authors/edit_author.php` and its 4 duplicate copies
 * (`backup/`, `broadcasting/`, `khotab/`, `chat/`'s own `edit_author.php`)
 * — Roadmap task 5.3, "one real permission-editor implementation," per
 * ADR-0010's explicit "Can be replaced" classification for this mechanism
 * (`admincp.md` §5 Pattern A: 3 of the 5 legacy copies are confirmed
 * broken — hardcoded-checked checkboxes ignoring real stored state, or a
 * commented-out save query). This is the one real implementation, not a
 * port of any of the 5.
 *
 * Permissions are grouped by module (decision-log #9's `{module}.{key}`
 * namespace) for the checkbox grid, matching the legacy UI's own
 * per-module portlet grouping (`edit_author.php:206-263`, one portlet per
 * `admincp/*\/menu.php` with a `menu.php`).
 *
 * Password reset uses `Hash::make()` (bcrypt) — the legacy working copy
 * (`authors/edit_author.php:61-68`) only ever wrote `md5($password)`,
 * confirmed the *only* password-set code path anywhere in `admincp/`
 * (`admincp.md` §8) — meaning no admin account could ever legitimately
 * receive a bcrypt hash through the legacy UI itself. Fixed here per
 * Blueprint §16 item 4/ADR-0010, not reproduced.
 *
 * `vbUser` (AdminCP Final Page-Level Visual-Parity Closure, 2026-08-22):
 * legacy `edit_author.php` also renders 2 real, DB-backed portlets this
 * class previously omitted — a profile-sidebar (avatar/name/vBulletin
 * rank) and a profile-content member-stats block (post count, post rate,
 * last activity/post, join date), both sourced from a single read-only
 * `SELECT * FROM user WHERE userid = {uid}` against vBulletin's own
 * database. Reconstructed here via the existing, already-real-only
 * `VbUser` model (`connection = 'vbulletin'`) — no new database
 * connection, no write access, no permission/auth semantics touched.
 * Legacy's "حذف كمشرف" button in the same sidebar is a plain `<button>`
 * with no handler/action anywhere — confirmed dead, not reproduced.
 */
class PermissionController
{
    public function edit(AdminUser $admin): View
    {
        $permissionsByModule = Permission::where('guard_name', 'admin')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0]);

        $assigned = $admin->getPermissionNames();

        $vbUser = $admin->uid ? VbUser::find($admin->uid) : null;

        return view('admin.permissions.edit', compact('admin', 'permissionsByModule', 'assigned', 'vbUser'));
    }

    public function update(Request $request, AdminUser $admin): RedirectResponse
    {
        $names = array_keys(array_filter($request->input('permissions', [])));
        $permissions = Permission::where('guard_name', 'admin')->whereIn('name', $names)->get();

        $admin->syncPermissions($permissions);

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم تحديث الصلاحيات بنجاح');
    }

    public function updatePassword(Request $request, AdminUser $admin): RedirectResponse
    {
        $request->validate(['new_password' => ['required', 'string', 'min:8']]);

        $admin->forceFill(['password' => Hash::make($request->string('new_password'))])->save();

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم تعيين كلمة مرور جديدة بنجاح');
    }
}
