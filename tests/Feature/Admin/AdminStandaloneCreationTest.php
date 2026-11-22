<?php

use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/*
 * Owner decision (2026-11-21): a forum membership is NOT required to create an administrator. An empty forum member
 * id creates a standalone administrator (`uid = 0`, no forum lookup) with its own login name, name, email and
 * password; a given id keeps the forum-linked flow unchanged.
 *
 * `nuke_authors` here has the REAL column shape for the fields that matter: `uid` INT NOT NULL with no default and
 * `thumb` NOT NULL (the shared fixture keeps both nullable for unrelated tests), so every test below also proves the
 * creation flow is compatible with the production schema.
 */
function saRealAuthorsTable(): Closure
{
    return function (Blueprint $table) {
        $table->increments('id');
        $table->integer('uid');                       // NOT NULL, no default — as production
        $table->string('aid', 25);
        $table->string('name', 50)->nullable();
        $table->string('email')->nullable();
        $table->string('pwd', 40)->nullable();
        $table->text('password')->nullable();
        $table->boolean('radminsuper')->default(false);
        $table->string('API')->nullable();
        $table->string('url')->default('');
        $table->text('thumb');                        // NOT NULL — as production
    };
}

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => saRealAuthorsTable(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
        'admin_account_states' => MainSchema::adminAccountStates(),
    ]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    foreach (['authors.liststuff', 'authors.addstuff', 'khotab.additem', 'fatwa.manage'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
    }
});

function saActor(string $aid = 'adder', bool $super = false, int $uid = 501): AdminUser
{
    $admin = AdminUser::on('main')->create([
        'aid' => $aid, 'uid' => $uid, 'name' => $aid, 'email' => $aid.'@test.invalid', 'thumb' => 'x',
        'password' => Hash::make($aid.'-pass'), 'radminsuper' => $super,
    ]);
    $admin->givePermissionTo('authors.addstuff');
    if ($super) {
        $admin->assignRole('super-admin');
    }

    return $admin;
}

/** The forum database is unreachable: a standalone creation must not notice (it never queries it). */
function saForumDown(): void
{
    config(['database.connections.vbulletin' => [
        'driver' => 'mysql', 'host' => '', 'port' => '3306', 'database' => '', 'username' => '', 'password' => '',
        'unix_socket' => sys_get_temp_dir().'/w2a-no-such-forum.sock', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
    ]]);
    DB::purge('vbulletin');
}

/** @return array<string, string> */
function saForm(array $overrides = []): array
{
    return array_merge([
        'vbuid' => '', 'aid' => 'standalone1', 'name' => 'مشرف مستقل', 'email' => 'standalone1@example.com', 'url' => '',
        'new_password' => 'Str0ng-pass!', 'new_password_confirmation' => 'Str0ng-pass!',
    ], $overrides);
}

function saCreated(string $aid): ?AdminUser
{
    return AdminUser::on('main')->where('aid', $aid)->first();
}

// ---- 1. standalone creation -----------------------------------------------------------------------------------

it('creates a standalone administrator without a forum member id: uid 0, no forum lookup, its own data, a hashed password, no grants, audited', function () {
    $this->actingAs(saActor(), 'admin');
    saForumDown(); // any forum query would fail — none may run

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), saForm())
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

    $admin = saCreated('standalone1');
    expect($admin)->not->toBeNull()
        ->and((int) $admin->uid)->toBe(0)
        ->and($admin->name)->toBe('مشرف مستقل')
        ->and($admin->email)->toBe('standalone1@example.com')
        ->and($admin->thumb)->toBe(AdminStaffController::DEFAULT_AVATAR)
        ->and((string) $admin->password)->not->toBe('Str0ng-pass!')
        ->and(Hash::check('Str0ng-pass!', (string) $admin->password))->toBeTrue()
        ->and((int) $admin->radminsuper)->toBe(0)
        ->and($admin->getRoleNames()->all())->toBe([])
        ->and($admin->getPermissionNames()->all())->toBe([]);

    $audit = DB::connection('main')->table('admin_audit_log')->where('action', 'create_admin')->sole();
    $meta = json_decode((string) $audit->metadata, true);
    expect($meta['forum_uid'])->toBeNull()
        ->and($meta['standalone'])->toBeTrue()
        ->and($meta['nickname_is_forum_username'])->toBeFalse()
        ->and($meta['initial_password_set'])->toBeTrue()
        ->and((string) $audit->metadata)->not->toContain('Str0ng-pass!');
});

it('omitting the forum member id entirely is the same as leaving it empty', function () {
    $this->actingAs(saActor(), 'admin');
    $form = saForm();
    unset($form['vbuid']);

    $this->post(route('admin.staff.store'), $form)->assertRedirect()->assertSessionHasNoErrors();

    expect((int) saCreated('standalone1')->uid)->toBe(0);
});

it('a standalone administrator requires login name, name, email and a confirmed password (Arabic messages, nothing created, no forum lookup)', function (string $field, mixed $value, string $errorKey, string $message) {
    $this->actingAs(saActor(), 'admin');
    saForumDown();

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), saForm([$field => $value]))
        ->assertRedirect(route('admin.staff.create'))
        ->assertSessionHasErrors([$errorKey => $message]);

    expect(AdminUser::on('main')->count())->toBe(1);
})->with([
    'no login name' => ['aid', '', 'aid', 'الكنية (اسم الدخول) مطلوبة عند إضافة مشرف مستقل بدون رقم عضو بالمنتدى'],
    'no name' => ['name', '', 'name', 'الإسم مطلوب عند إضافة مشرف مستقل بدون رقم عضو بالمنتدى'],
    'no email' => ['email', '', 'email', 'البريد الالكتروني مطلوب عند إضافة مشرف مستقل بدون رقم عضو بالمنتدى'],
    'no password' => ['new_password', '', 'new_password', 'كلمة المرور مطلوبة عند إضافة مشرف مستقل بدون رقم عضو بالمنتدى'],
]);

it('a standalone password must be at least 8 characters and confirmed', function () {
    $this->actingAs(saActor(), 'admin');

    $this->post(route('admin.staff.store'), saForm(['new_password' => 'short', 'new_password_confirmation' => 'short']))
        ->assertSessionHasErrors('new_password');
    $this->post(route('admin.staff.store'), saForm(['new_password_confirmation' => 'something-else']))
        ->assertSessionHasErrors('new_password');

    expect(AdminUser::on('main')->count())->toBe(1);
});

// ---- 2. several standalone administrators ---------------------------------------------------------------------

it('several standalone administrators coexist: uid 0 is never treated as a duplicate', function () {
    $this->actingAs(saActor(), 'admin');

    foreach ([1, 2, 3] as $n) {
        $this->post(route('admin.staff.store'), saForm(['aid' => "standalone{$n}", 'name' => "مستقل {$n}", 'email' => "s{$n}@example.com"]))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
    }

    expect(AdminUser::on('main')->where('uid', 0)->count())->toBe(3)
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'create_admin')->count())->toBe(3);
});

// ---- 3 / 4 / 9. forum-linked mode unchanged --------------------------------------------------------------------

it('forum-linked creation is unchanged: the member is validated, empty fields default to the forum account, uid is the member id', function () {
    $this->actingAs(saActor(), 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'forumguy', 'email' => 'forum@example.com']);

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

    $admin = AdminUser::on('main')->where('uid', 99)->sole();
    expect($admin->aid)->toBe('forumguy')
        ->and($admin->name)->toBe('forumguy')
        ->and($admin->email)->toBe('forum@example.com')
        ->and($admin->thumb)->toBe(AdminStaffController::DEFAULT_AVATAR)   // forum member without an avatar
        ->and((int) $admin->radminsuper)->toBe(0);
    $meta = json_decode((string) DB::connection('main')->table('admin_audit_log')->where('action', 'create_admin')->value('metadata'), true);
    expect($meta['forum_uid'])->toBe(99)->and($meta['standalone'])->toBeFalse()->and($meta['nickname_is_forum_username'])->toBeTrue();
});

it('an invalid forum member is still refused: unknown id, zero, negative, non-numeric — nothing created', function () {
    $this->actingAs(saActor(), 'admin');

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => '123456'])
        ->assertSessionHas('error', 'هذا العضو غير موجود بالمنتدى');
    foreach (['0', '-3', 'abc'] as $bad) {
        $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), saForm(['vbuid' => $bad]))
            ->assertSessionHasErrors('vbuid');
    }

    expect(AdminUser::on('main')->count())->toBe(1);
});

it('linked duplicate checks still apply next to standalone administrators', function () {
    $this->actingAs(saActor(), 'admin');
    DB::connection('vbulletin')->table('user')->insert([
        ['userid' => 99, 'username' => 'forumguy', 'email' => 'forum@example.com'],
        ['userid' => 77, 'username' => 'other', 'email' => 'o@example.com'],
    ]);
    $this->post(route('admin.staff.store'), saForm())->assertSessionMissing('error');            // a standalone (uid 0)
    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertSessionMissing('error');  // linked

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertSessionHas('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');
    // a linked nickname may still not be another forum member's username
    $this->post(route('admin.staff.store'), ['vbuid' => '77', 'aid' => 'forumguy'])->assertSessionHas('error');

    expect(AdminUser::on('main')->where('uid', 99)->count())->toBe(1)
        ->and(AdminUser::on('main')->where('uid', 0)->count())->toBe(1)
        ->and(AdminUser::on('main')->where('uid', 77)->exists())->toBeFalse();
});

// ---- 5. login --------------------------------------------------------------------------------------------------

it('a standalone administrator logs in with its login name and password, without the forum', function () {
    $this->actingAs(saActor(), 'admin');
    $this->post(route('admin.staff.store'), saForm())->assertSessionMissing('error');
    app('auth')->forgetGuards();
    $this->app['session']->flush();
    saForumDown();

    $this->post('/admincp/login', ['aid' => 'standalone1', 'password' => 'wrong-password'])->assertSessionHasErrors('aid');
    $this->post('/admincp/login', ['aid' => 'standalone1', 'password' => 'Str0ng-pass!'])->assertRedirect(route('admin.entry'));

    $this->assertAuthenticatedAs(saCreated('standalone1'), 'admin');
});

// ---- 6. roles and permissions ----------------------------------------------------------------------------------

it('permissions and the super-admin role are assigned to a standalone administrator only by authorised actors', function () {
    // a non-super-admin may not assign permissions
    $plain = saActor('plain');
    $this->actingAs($plain, 'admin');
    $this->post(route('admin.staff.store'), saForm(['permissions' => ['fatwa.manage' => '1']]))
        ->assertSessionHas('error', 'تعيين الصلاحيات متاح للمشرف العام فقط.');
    expect(saCreated('standalone1'))->toBeNull();

    // a super-admin (not an owner) assigns permissions, but may not create a super-admin
    app('auth')->forgetGuards();
    $super = saActor('super', true, 502);
    $this->actingAs($super, 'admin');
    $this->post(route('admin.staff.store'), saForm(['aid' => 'sa2', 'name' => 'مستقل 2', 'make_super_admin' => '1']))
        ->assertSessionHas('error', 'إضافة مشرف عام متاحة لحسابات الملاك فقط.');
    $this->post(route('admin.staff.store'), saForm(['permissions' => ['fatwa.manage' => '1', 'khotab.additem' => '1']]))
        ->assertSessionMissing('error');
    $withPerms = saCreated('standalone1');
    expect($withPerms->getPermissionNames()->sort()->values()->all())->toBe(['fatwa.manage', 'khotab.additem'])
        ->and($withPerms->hasRole('super-admin'))->toBeFalse()
        ->and((int) $withPerms->radminsuper)->toBe(0);

    // an owner creates a standalone super-admin (Laravel role only; legacy radminsuper stays 0)
    app('auth')->forgetGuards();
    $owner = saActor('ownerx', true, 503);
    config(['admin_owners.owner_aids' => ['Admin', 'مؤمن يحب الله ورسوله', 'ownerx']]);
    $this->actingAs($owner, 'admin');
    $this->post(route('admin.staff.store'), saForm(['aid' => 'sa3', 'name' => 'مستقل 3', 'email' => 's3@example.com', 'make_super_admin' => '1']))
        ->assertSessionMissing('error');
    $superStandalone = saCreated('sa3');
    expect($superStandalone->hasRole('super-admin'))->toBeTrue()
        ->and((int) $superStandalone->radminsuper)->toBe(0)
        ->and((int) $superStandalone->uid)->toBe(0);
});

// ---- 8. duplicate / reserved identities --------------------------------------------------------------------------

it('standalone creation refuses a duplicate login name, a duplicate name and the reserved owner identities', function () {
    $this->actingAs(saActor(), 'admin');
    $this->post(route('admin.staff.store'), saForm())->assertSessionMissing('error');

    $this->post(route('admin.staff.store'), saForm(['name' => 'اسم آخر', 'email' => 'x@example.com']))
        ->assertSessionHas('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
    $this->post(route('admin.staff.store'), saForm(['aid' => 'another']))
        ->assertSessionHas('error', 'يوجد مشرف آخر بنفس الإسم، اختر إسمًا آخر');
    $this->post(route('admin.staff.store'), saForm(['aid' => 'adder', 'name' => 'جديد']))   // the actor's own login name
        ->assertSessionHas('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
    foreach ([['aid' => 'Good', 'name' => 'مختلف'], ['aid' => 'x9', 'name' => 'Good'], ['aid' => 'Admin', 'name' => 'مختلف 2']] as $reserved) {
        $this->post(route('admin.staff.store'), saForm($reserved))
            ->assertSessionHas('error', 'هذه الكنية أو هذا الإسم محجوز لحسابات الملاك؛ اختر غيره.');
    }
    $this->post(route('admin.staff.store'), saForm(['aid' => '<b>x</b>', 'name' => 'وسم']))->assertSessionHasErrors('aid');

    expect(AdminUser::on('main')->where('uid', 0)->count())->toBe(1);
});

// ---- 10. schema compatibility ----------------------------------------------------------------------------------

it('the production column shape is enforced here (uid NOT NULL, no default), and every creation writes an explicit uid', function () {
    expect(fn () => DB::connection('main')->table('nuke_authors')->insert(['aid' => 'nouid', 'thumb' => 'x']))
        ->toThrow(QueryException::class);

    $this->actingAs(saActor(), 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'forumguy', 'email' => 'forum@example.com']);
    $this->post(route('admin.staff.store'), saForm())->assertSessionMissing('error');
    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertSessionMissing('error');

    expect(DB::connection('main')->table('nuke_authors')->whereNull('uid')->count())->toBe(0)
        ->and(DB::connection('main')->table('nuke_authors')->orderBy('id')->pluck('uid')->map(fn ($u) => (int) $u)->all())->toBe([501, 0, 99]);
});

// ---- UI --------------------------------------------------------------------------------------------------------

it('the create form explains both modes and no longer requires the forum member id', function () {
    $this->actingAs(saActor(), 'admin');

    $html = $this->get(route('admin.staff.create'))->assertOk()->getContent();

    expect($html)->toContain('data-admin-create-modes')
        ->toContain('رقم العضو بالمنتدى (اختياري)')
        ->toContain('اتركه فارغًا لإضافة مشرف مستقل بدون عضوية بالمنتدى')
        ->toContain('مطلوبة للمشرف المستقل')
        ->not->toContain('name="vbuid" required');
});
