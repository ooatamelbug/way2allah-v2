<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Roadmap tasks 5.6/5.7. Consolidates `authors/index.php` (dead add-flow)
 * and `backup/index.php` (working but hardcoded `'way2allah'` password)
 * into one real implementation with no fixed default password.
 */
function useInMemoryConnectionsForAdminStaff(): void
{
    // `admin_audit_log`: adding an admin is audited (`create_admin`) since administrator management (2026-11-17).
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors(), 'admin_audit_log' => MainSchema::adminAuditLog()]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
}

beforeEach(function () {
    useInMemoryConnectionsForAdminStaff();
});

it('lists staff', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    AdminUser::on('main')->create(['aid' => 'colleague', 'password' => 'x']);

    $this->get(route('admin.staff.index'))->assertOk()->assertSee('colleague');
});

it('AdminCP Final 12-Route Browser Visual Evidence (2026-08-23): renders each staff member\'s real avatar thumbnail inline with their name, matching authors/index.php:251', function () {
    InMemoryConnection::setup('main', [
        // `thumb` is part of the shared fixture since administrator management (2026-11-17).
        'nuke_authors' => MainSchema::nukeAuthors(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
    ]);
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    AdminUser::on('main')->create(['aid' => 'colleague', 'password' => 'x'])->forceFill(['thumb' => '/images/avatars/colleague.png'])->save();

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->toContain('<img class="user-pic" src="/images/avatars/colleague.png">');
});

// ---- Global Authenticated Design/CSS Parity (2026-08-23) ----

it('the create page uses real Metronic form-control/btn classes, matching authors/index.php\'s real add-staff form', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $content = $this->get(route('admin.staff.create'))->assertOk()->getContent();

    // Owner decision 2026-11-21: the forum member id is optional (empty = standalone administrator).
    expect($content)->toContain('name="vbuid" inputmode="numeric" class="form-control"')
        ->not->toContain('name="vbuid" required')
        ->toContain('class="btn green"');
});

it('a regression test proving the new add-flow actually persists an AdminUser — proving the fix, not authors/index.php\'s die(\'hhhh\')', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com']);

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect();

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeTrue();
});

it('task 5.7 fix: the new admin never receives the legacy hardcoded default password', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com']);

    $this->post(route('admin.staff.store'), ['vbuid' => '99']);

    $created = AdminUser::on('main')->where('uid', 99)->first();
    expect($created->password)->not->toBe(md5('way2allah'))
        ->and(str_starts_with($created->password, '$2y$'))->toBeTrue()
        ->and(Hash::check('way2allah', $created->password))->toBeFalse();
});

it('rejects adding a vBulletin member who is already an admin', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'me', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'existing', 'email' => 'e@example.com']);
    AdminUser::on('main')->create(['uid' => 99, 'aid' => 'existing', 'password' => 'x']);

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect();

    expect(AdminUser::on('main')->where('uid', 99)->count())->toBe(1);
});

// ---- Add administrator: forum-database failures and member-id validation (AdminCP feedback, 2026-11-18) ----

function staffAdder(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'adder', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));

    return $admin;
}

/** The production failure shape: an unconfigured forum connection (empty host, no socket) → SQLSTATE[HY000] [2002]. */
function makeForumDatabaseUnavailable(): void
{
    config(['database.connections.vbulletin' => [
        'driver' => 'mysql', 'host' => '', 'port' => '3306', 'database' => '', 'username' => '', 'password' => '',
        'unix_socket' => sys_get_temp_dir().'/w2a-no-such-forum.sock', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
    ]]);
    DB::purge('vbulletin');
}

it('forum database unavailable: back to the add form with the Arabic message and the entered id; nothing created or audited; logged without connection details', function () {
    $this->actingAs(staffAdder(), 'admin');
    makeForumDatabaseUnavailable();
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = [$event->level, $event->message, $event->context];
    });

    $this->from(route('admin.staff.create'))
        ->post(route('admin.staff.store'), ['vbuid' => '99', 'email' => 'keep@example.com', 'new_password' => 'secret-pass-1', 'new_password_confirmation' => 'secret-pass-1'])
        ->assertRedirect(route('admin.staff.create'))
        ->assertSessionHas('error', 'تعذر الاتصال بقاعدة بيانات المنتدى، لم تتم إضافة أي مشرف')
        ->assertSessionHasInput('vbuid', '99')
        ->assertSessionHasInput('email', 'keep@example.com')
        ->assertSessionMissing('_old_input.new_password');

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeFalse()
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
    $entry = collect($logged)->firstWhere(1, 'admin-create: forum database unavailable — no administrator added');
    expect($entry)->not->toBeNull()
        ->and($entry[0])->toBe('error')
        ->and($entry[2])->toBe(['operation' => 'member_lookup', 'exception' => QueryException::class, 'code' => '2002'])
        ->and(json_encode($entry[2]))->not->toContain('w2a-no-such-forum')->not->toContain('SQLSTATE');

    // The add form itself does not need the forum database and shows the message with the id kept.
    $this->get(route('admin.staff.create'))->assertOk();
});

it('forum database failing on the nickname-collision lookup: same controlled refusal, nothing created', function () {
    $this->actingAs(staffAdder(), 'admin');
    DB::connection('vbulletin')->table('user')->insert(['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com']);
    DB::connection('vbulletin')->beforeExecuting(function (string $sql, array $bindings) {
        if (str_contains($sql, '"username" =')) {
            throw new QueryException('vbulletin', $sql, $bindings, new PDOException('SQLSTATE[HY000] [2002] No such file or directory'));
        }
    });

    $this->from(route('admin.staff.create'))
        ->post(route('admin.staff.store'), ['vbuid' => '99', 'aid' => 'different-nick'])
        ->assertRedirect(route('admin.staff.create'))
        ->assertSessionHas('error', 'تعذر الاتصال بقاعدة بيانات المنتدى، لم تتم إضافة أي مشرف')
        ->assertSessionHasInput('aid', 'different-nick');

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeFalse()
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
});

it('a GIVEN forum member id must be a positive integer (clear Arabic message, nothing looked up or created)', function (mixed $vbuid, string $message) {
    $this->actingAs(staffAdder(), 'admin');
    makeForumDatabaseUnavailable(); // proves validation runs before any forum lookup

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => $vbuid])
        ->assertRedirect(route('admin.staff.create'))
        ->assertSessionHasErrors(['vbuid' => $message]);

    expect(AdminUser::on('main')->count())->toBe(1);
})->with([
    // 'empty' is no longer an error: it selects the standalone mode (AdminStandaloneCreationTest).
    'letters' => ['abc', 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا'],
    'decimal' => ['1.5', 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا'],
    'zero' => ['0', 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا موجبًا، أو اتركه فارغًا لإضافة مشرف مستقل'],
    'negative' => ['-5', 'رقم العضو بالمنتدى يجب أن يكون رقمًا صحيحًا موجبًا، أو اتركه فارغًا لإضافة مشرف مستقل'],
]);

it('unchanged: a valid member is added; the same member again, an existing admin and a nonexistent member are refused with their messages', function () {
    $this->actingAs(staffAdder(), 'admin');
    DB::connection('vbulletin')->table('user')->insert([
        ['userid' => 99, 'username' => 'newstaff', 'email' => 'new@example.com'],
        ['userid' => 77, 'username' => 'alreadyadmin', 'email' => 'a@example.com'],
    ]);
    AdminUser::on('main')->create(['aid' => 'alreadyadmin', 'uid' => 77, 'password' => 'x']);

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect()->assertSessionHasNoErrors();
    $created = AdminUser::on('main')->where('uid', 99)->firstOrFail();
    expect((int) $created->radminsuper)->toBe(0)
        ->and($created->getRoleNames()->all())->toBe([])
        ->and($created->getPermissionNames()->all())->toBe([])
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'create_admin')->count())->toBe(1);

    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => '99'])
        ->assertSessionHas('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');
    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => '77'])
        ->assertSessionHas('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');
    $this->from(route('admin.staff.create'))->post(route('admin.staff.store'), ['vbuid' => '123456'])
        ->assertSessionHas('error', 'هذا العضو غير موجود بالمنتدى');

    expect(AdminUser::on('main')->where('uid', 99)->count())->toBe(1)
        ->and(DB::connection('main')->table('admin_audit_log')->where('action', 'create_admin')->count())->toBe(1);
});
