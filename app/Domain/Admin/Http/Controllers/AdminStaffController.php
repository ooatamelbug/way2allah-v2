<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Staff\AdminDeactivationRefused;
use App\Domain\Admin\Actions\Staff\AdminManagementRefused;
use App\Domain\Admin\Actions\Staff\AdminStaffSafety;
use App\Domain\Admin\Actions\Staff\ChangeSuperAdminRoleAction;
use App\Domain\Admin\Actions\Staff\DeactivateAdminAction;
use App\Domain\Admin\Actions\Staff\ReactivateAdminAction;
use App\Domain\Admin\Actions\Staff\RenameAdminAction;
use App\Domain\Admin\Actions\Staff\UpdateAdminProfileAction;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminDeactivation;
use App\Domain\Admin\Support\AdminGrantInspector;
use App\Domain\Admin\Support\AdminManagementPolicy;
use App\Domain\Admin\Support\AdminOwners;
use App\Support\Permission\Permission;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
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
        $staff = AdminUser::on('main')->orderByDesc('radminsuper')->orderBy('aid')->get(['id', 'uid', 'aid', 'name', 'email', 'radminsuper', 'thumb']);

        // F02: deactivated admins stay listed (never deleted) with a status badge; empty while the feature is off.
        $deactivatedIds = AdminDeactivation::deactivatedIds();

        // F02-UI (owner-approved, 2026-11-14): controls are rendered only for viewers whose request would be allowed —
        // the permission editor is `admin.role:super-admin` (routes/admin.php), adding staff is
        // `admin.permission:authors.addstuff`. The route middleware still enforces both; this only stops showing
        // links that end in a 403.
        $viewer = self::viewer();
        $canEditPermissions = $viewer->hasRole('super-admin');
        $canAddStaff = $canEditPermissions || $viewer->hasAnyPermission(['authors.addstuff']);
        $canManageStatus = $canEditPermissions && AdminDeactivation::enabled();
        $viewerId = (int) $viewer->getKey();

        // Administrator management: owner / Laravel super-admin badges; the deactivate link only where the policy
        // would allow it (DeactivateAdminAction enforces it again).
        $ownerIds = $staff->filter(fn (AdminUser $a) => AdminOwners::isOwner($a))->map(fn (AdminUser $a) => (int) $a->id)->values()->all();
        $protectedIds = $staff->filter(fn (AdminUser $a) => AdminOwners::isProtectedMainAccount($a))->map(fn (AdminUser $a) => (int) $a->id)->values()->all();
        $laravelSuperAdminIds = AdminStaffSafety::spatieSuperAdminIds();
        $manageableIds = $canManageStatus
            ? $staff->filter(fn (AdminUser $a) => AdminManagementPolicy::manageRefusal($viewer, $a) === null)->map(fn (AdminUser $a) => (int) $a->id)->values()->all()
            : [];

        return view('admin.staff.index', compact('staff', 'canEditPermissions', 'canAddStaff', 'deactivatedIds', 'canManageStatus', 'viewerId', 'ownerIds', 'protectedIds', 'laravelSuperAdminIds', 'manageableIds'));
    }

    /** F02: confirmation page (super-admin, feature enabled). */
    public function confirmDeactivate(AdminUser $admin): View
    {
        self::requireEnabled();

        return view('admin.staff.deactivate', ['target' => $admin, 'isSelf' => (int) $admin->getKey() === (int) self::viewer()->getKey()]);
    }

    public function deactivate(Request $request, AdminUser $admin, DeactivateAdminAction $action): RedirectResponse
    {
        self::requireEnabled();
        $data = $request->validate([
            // Plain text only: the audit recorder refuses markup in metadata.
            'reason' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'confirm' => ['accepted'],
        ], [], ['reason' => 'سبب التعطيل', 'confirm' => 'التأكيد']);

        try {
            $action->execute(self::viewer(), $admin, trim((string) $data['reason']), $request->ip());
        } catch (AdminDeactivationRefused $e) {
            return redirect()->route('admin.staff.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.staff.index')->with('success', 'تم تعطيل المشرف '.$admin->aid.'. تم إيقاف دخوله إلى لوحة التحكم الجديدة وإبطال كلمات مروره ومفتاح النسخ الاحتياطي. تنبيه: الأنظمة القديمة (OLD / جسر الدخول) لا تقرأ حالة التعطيل بعد.');
    }

    /** F02: reactivation page (super-admin, feature enabled). A new password is required. */
    public function confirmReactivate(AdminUser $admin): View
    {
        self::requireEnabled();

        return view('admin.staff.reactivate', ['target' => $admin]);
    }

    public function reactivate(Request $request, AdminUser $admin, ReactivateAdminAction $action): RedirectResponse
    {
        self::requireEnabled();
        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [], ['new_password' => 'كلمة المرور الجديدة']);

        try {
            $action->execute(self::viewer(), $admin, (string) $data['new_password'], $request->ip());
        } catch (AdminDeactivationRefused $e) {
            return redirect()->route('admin.staff.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.staff.index')->with('success', 'تمت إعادة تفعيل المشرف '.$admin->aid.' بكلمة مرور جديدة.');
    }

    private static function requireEnabled(): void
    {
        if (! AdminDeactivation::enabled()) {
            abort(404);
        }
    }

    public function create(): View
    {
        $viewer = self::viewer();
        $canAssignPermissions = $viewer->hasRole('super-admin');
        $permissionsByModule = $canAssignPermissions
            ? Permission::where('guard_name', 'admin')->get()->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            : collect();
        $canGrantSuperAdmin = AdminManagementPolicy::mayGrantSuperAdminOnCreate($viewer);

        return view('admin.staff.create', compact('canAssignPermissions', 'permissionsByModule', 'canGrantSuperAdmin'));
    }

    /**
     * `backup/index.php:93-140`'s working add-flow, rebuilt: same vBulletin-member-id lookup and already-an-admin guard, no hardcoded password.
     *
     * Administrator management (OLD `mod_authors` → `AddAuthor` parity, 2026-11-17): the form also takes the
     * nickname (`aid`, OLD «الكنية», the login name), the name (OLD «الإسم»), email, URL, an optional initial
     * password, module permissions (super-admin actors) and the super-admin flag (owner actors). Every field is
     * optional and defaults to the forum account (Gen 2 behaviour), so a vbuid-only request behaves as before.
     * Forum identity is preserved: the row is always linked to the forum member by `uid`; a nickname that differs
     * from the member's forum username must not be ANOTHER forum member's username, and nickname / name must be
     * unique among admins. Audited as `create_admin` inside the creation transaction.
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
     *  - Requested permissions / the super-admin role are written only AFTER that commit (Spatie lives on another
     *    connection), so a rolled-back creation can never leave grants behind under an unused id.
     */
    public function store(Request $request, AdminGrantInspector $grants, AdminAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'vbuid' => ['required', 'integer', 'min:1'],
            'aid' => ['nullable', 'string', 'max:25', 'not_regex:/[<>\x00-\x1F]/'],
            'name' => ['nullable', 'string', 'max:50', 'not_regex:/[<>\x00-\x1F]/'],
            'email' => ['nullable', 'email', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
            'permissions' => ['nullable', 'array'],
            'make_super_admin' => ['nullable', 'boolean'],
        ], [
            'vbuid.required' => 'من فضلك أدخل رقم العضو بالمنتدى',
            'vbuid.integer' => 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا',
            'vbuid.min' => 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا موجبًا',
        ], [
            'vbuid' => 'رقم العضو بالمنتدى', 'aid' => 'الكنية', 'name' => 'الإسم', 'email' => 'البريد الالكتروني', 'url' => 'الرابط', 'new_password' => 'كلمة المرور',
        ]);

        $viewer = self::viewer();
        $requestedPermissions = array_keys(array_filter((array) ($data['permissions'] ?? [])));
        $makeSuperAdmin = (bool) ($data['make_super_admin'] ?? false);

        // Escalation guards: only a super-admin assigns permissions at creation (as in the permission editor), only an
        // owner creates a super-admin. Refused before anything is written.
        if ($requestedPermissions !== [] && ! $viewer->hasRole('super-admin')) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'تعيين الصلاحيات متاح للمشرف العام فقط.');
        }
        if ($makeSuperAdmin && ! AdminManagementPolicy::mayGrantSuperAdminOnCreate($viewer)) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'إضافة مشرف عام متاحة لحسابات الملاك فقط.');
        }
        $permissions = $requestedPermissions === [] ? collect() : Permission::where('guard_name', 'admin')->whereIn('name', $requestedPermissions)->get();
        if ($permissions->count() !== count($requestedPermissions)) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'صلاحية غير معروفة.');
        }

        $vbUserId = (int) $data['vbuid'];

        // Both forum lookups run before the creation lock / transaction, so a forum-database failure writes nothing.
        try {
            $vbUser = DB::connection('vbulletin')->table('user')->where('userid', $vbUserId)->first(['userid', 'username', 'email', 'avatarid']);
        } catch (QueryException|PDOException $e) {
            return self::forumUnavailable($request, 'member_lookup', $e);
        }

        if ($vbUser === null) {
            return back()->with('error', 'هذا العضو غير موجود بالمنتدى');
        }

        $aid = trim((string) ($data['aid'] ?? '')) !== '' ? trim((string) $data['aid']) : (string) $vbUser->username;
        $name = trim((string) ($data['name'] ?? '')) !== '' ? trim((string) $data['name']) : (string) $vbUser->username;
        $email = trim((string) ($data['email'] ?? '')) !== '' ? trim((string) $data['email']) : (string) $vbUser->email;
        $url = trim((string) ($data['url'] ?? ''));
        $password = (string) ($data['new_password'] ?? '');

        if (mb_strlen($aid) > 25 || mb_strlen($name) > 50) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'اسم العضو بالمنتدى أطول من المسموح به للكنية (25 حرفًا) أو الإسم (50 حرفًا)؛ أدخل كنية أو إسمًا أقصر.');
        }
        if (preg_match('/[<>\x00-\x1F]/', $aid.$name) === 1) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'اسم العضو بالمنتدى يحتوي على رموز غير مسموحة؛ أدخل كنية وإسمًا بديلين.');
        }
        // OLD's authority identities (owner login names, the `Good` main account) cannot be created here: a new
        // account named `Good` would be undeletable, and an owner name would make it an owner once `radminsuper` is set.
        if (AdminOwners::isReservedIdentity($aid, $name)) {
            return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'هذه الكنية أو هذا الإسم محجوز لحسابات الملاك؛ اختر غيره.');
        }
        // Forum identity: a nickname other than the member's own username must not be another member's username.
        if ($aid !== (string) $vbUser->username) {
            try {
                $nicknameTaken = DB::connection('vbulletin')->table('user')->where('username', $aid)->where('userid', '!=', $vbUser->userid)->exists();
            } catch (QueryException|PDOException $e) {
                return self::forumUnavailable($request, 'nickname_collision_lookup', $e);
            }
            if ($nicknameTaken) {
                return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'هذه الكنية اسم مستخدم لعضو آخر بالمنتدى؛ اختر كنية أخرى.');
            }
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

            if (AdminUser::on('main')->where('aid', $aid)->exists()) {
                return back()->with('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
            }

            if (AdminUser::on('main')->where('name', $name)->exists()) {
                return back()->withInput($request->except('new_password', 'new_password_confirmation'))->with('error', 'يوجد مشرف آخر بنفس الإسم، اختر إسمًا آخر');
            }

            $main = DB::connection('main');
            $main->beginTransaction();

            try {
                $admin = AdminUser::on('main')->create([
                    'uid' => $vbUser->userid,
                    'aid' => $aid,
                    'name' => $name,
                    'email' => $email,
                    'url' => $url,
                    'thumb' => self::forumAvatar($vbUser),
                    'password' => Hash::make($password !== '' ? $password : Str::random(32)),
                    'radminsuper' => 0,
                ]);

                $existing = $grants->grantCountsFor((int) $admin->getKey());

                if ($existing['roles'] + $existing['permissions'] === 0) {
                    $audit->record($viewer, 'create_admin', 'authors', 'admin', (int) $admin->getKey(), [
                        'target_aid' => $aid,
                        'forum_uid' => (int) $vbUser->userid,
                        'nickname_is_forum_username' => $aid === (string) $vbUser->username,
                        'initial_password_set' => $password !== '',
                        'permissions' => $permissions->pluck('name')->sort()->values()->all(),
                        'super_admin' => $makeSuperAdmin,
                    ], $request->ip());
                }
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

        try {
            if ($permissions->isNotEmpty()) {
                $admin->syncPermissions($permissions);
            }
            if ($makeSuperAdmin) {
                $admin->assignRole('super-admin');
            }
        } catch (Throwable $e) {
            Log::error('admin-create: the admin was created but its grants could not be written', ['admin_id' => (int) $admin->getKey(), 'exception' => $e::class]);

            return redirect()->route($viewer->hasRole('super-admin') ? 'admin.staff.index' : 'admin.staff.create')
                ->with('error', 'تم إضافة المشرف بدون صلاحيات: تعذر حفظ الصلاحيات المطلوبة. عيّنها من صفحة الصلاحيات.');
        }

        // F02-UI: only a super-admin can open the permission editor; anyone else would be sent straight into a 403.
        if ($viewer->hasRole('super-admin')) {
            $message = $password !== ''
                ? 'تم إضافة العضو كمشرف بالموقع بالبيانات والصلاحيات المحددة'
                : 'تم إضافة العضو كمشرف بالموقع، يمكنك الآن تعيين صلاحياته وكلمة مروره';

            return redirect()->route('admin.permissions.edit', $admin)->with('success', $message);
        }

        $message = 'تم إضافة العضو كمشرف بالموقع، تعيين صلاحياته وكلمة مروره يتم بواسطة الإدارة العليا للموقع';

        return $viewer->hasAnyPermission(['authors.liststuff'])
            ? redirect()->route('admin.staff.index')->with('success', $message)
            : redirect()->route('admin.staff.create')->with('success', $message);
    }

    /** OLD `mod_authors` → `UpdateAuthor` (profile part): email and URL. Super-admin; protected targets need an owner. */
    public function updateProfile(Request $request, AdminUser $admin, UpdateAdminProfileAction $action): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
        ], [], ['email' => 'البريد الالكتروني', 'url' => 'الرابط']);

        try {
            $action->execute(self::viewer(), $admin, trim((string) $data['email']), trim((string) ($data['url'] ?? '')), $request->ip());
        } catch (AdminManagementRefused $e) {
            return redirect()->route('admin.permissions.edit', $admin)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم تحديث بيانات المشرف');
    }

    /** OLD `mod_authors` → UpdateAuthor `aid`: rename the login name. Owners only; 404 while `admin_rename.enabled` is off. */
    public function rename(Request $request, AdminUser $admin, RenameAdminAction $action): RedirectResponse
    {
        if (! (bool) config('admin_rename.enabled', false)) {
            abort(404);
        }
        $data = $request->validate(['aid' => ['required', 'string', 'max:25', 'not_regex:/[<>\x00-\x1F]/']], [], ['aid' => 'اسم الدخول']);

        try {
            $action->execute(self::viewer(), $admin, (string) $data['aid'], $request->ip());
        } catch (AdminManagementRefused $e) {
            return redirect()->route('admin.permissions.edit', $admin)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم تعديل اسم الدخول. يستخدم المشرف الاسم الجديد للدخول من الآن.');
    }

    /** OLD `mod_authors` "Super User": grant Laravel's super-admin role. Owners only. */
    public function promote(Request $request, AdminUser $admin, ChangeSuperAdminRoleAction $action): RedirectResponse
    {
        try {
            $action->promote(self::viewer(), $admin, $request->ip());
        } catch (AdminManagementRefused $e) {
            return redirect()->route('admin.permissions.edit', $admin)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تمت ترقية '.$admin->aid.' إلى مشرف عام في لوحة التحكم الجديدة.');
    }

    public function demote(Request $request, AdminUser $admin, ChangeSuperAdminRoleAction $action): RedirectResponse
    {
        try {
            $action->demote(self::viewer(), $admin, $request->ip());
        } catch (AdminManagementRefused $e) {
            return redirect()->route('admin.permissions.edit', $admin)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.permissions.edit', $admin)->with('success', 'تم سحب صلاحية المشرف العام من '.$admin->aid.' في لوحة التحكم الجديدة.');
    }

    /**
     * The forum (vBulletin) database could not be queried: back to the add form with the entered values (never the
     * passwords) and a clear message; nothing has been written. Logged with the operation, exception class and SQLSTATE
     * code only — the exception message carries the host / database names, so it is not logged.
     */
    private static function forumUnavailable(Request $request, string $operation, QueryException|PDOException $e): RedirectResponse
    {
        Log::error('admin-create: forum database unavailable — no administrator added', [
            'operation' => $operation,
            'exception' => $e::class,
            'code' => (string) $e->getCode(),
        ]);

        return back()->withInput($request->except('new_password', 'new_password_confirmation'))
            ->with('error', 'تعذر الاتصال بقاعدة بيانات المنتدى، لم تتم إضافة أي مشرف');
    }

    /** Gen 2's add-flow avatar (`backup/index.php`): the forum avatar, else the site's default image. Read-only lookup. */
    private static function forumAvatar(object $vbUser): string
    {
        $default = 'https://way2allah.com/new/login-logo.png';
        if ((int) ($vbUser->avatarid ?? 0) <= 0) {
            return $default;
        }
        try {
            $path = DB::connection('vbulletin')->table('avatar')->where('avatarid', (int) $vbUser->avatarid)->value('avatarpath');
        } catch (Throwable) {
            return $default;
        }

        return is_string($path) && $path !== '' ? 'https://forums.way2allah.com/'.ltrim($path, '/') : $default;
    }

    /** The authenticated admin; the `admin.role` / `admin.permission` middleware guarantees one on these routes. */
    private static function viewer(): AdminUser
    {
        $viewer = auth('admin')->user();

        if (! $viewer instanceof AdminUser) {
            abort(401);
        }

        return $viewer;
    }
}
