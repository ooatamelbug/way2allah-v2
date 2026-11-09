<?php

/*
 * Admin grant safety (owner-approved, 2026-11-08):
 *  - Laravel admin creation refuses a new admin whose freshly allocated nuke_authors.id already carries
 *    Spatie grants (an id reused after a MySQL 5.7 restart would inherit them), rolls the insert back, and
 *    never deletes or reassigns the grants; duplicate usernames are refused; creation is serialised by a lock.
 *  - `admin:audit-grants` is read-only and reports orphan / unexpected / duplicate / super-admin findings.
 */

use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminGrantInspector;
use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

beforeEach(function () {
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
    Cache::flush();
});

function agsOperator(): AdminUser
{
    $admin = AdminUser::on('main')->create(['uid' => 1, 'aid' => 'operator', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.addstuff', 'guard_name' => 'admin']));

    return $admin;
}

function agsForumUser(int $id, string $username): void
{
    DB::connection('vbulletin')->table('user')->insert(['userid' => $id, 'username' => $username, 'email' => "{$username}@example.com"]);
}

function agsNextAdminId(): int
{
    return (int) DB::connection('main')->table('nuke_authors')->max('id') + 1;
}

/** Plant a stale grant under an admin id, as legacy deletion leaves behind. */
function agsPlantGrant(int $adminId, string $permission = 'khotab.edititem'): void
{
    $p = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
    DB::table('model_has_permissions')->insert(['permission_id' => $p->id, 'model_type' => AdminUser::class, 'model_id' => $adminId]);
}

function agsAudit(array $options = []): array
{
    $code = Artisan::call('admin:audit-grants', $options);

    return [$code, Artisan::output()];
}

// ================================================================ creation

it('creates an admin normally: row saved, no grants, redirect to the permission editor', function () {
    $this->actingAs(agsOperator(), 'admin');
    agsForumUser(99, 'newstaff');

    $response = $this->post(route('admin.staff.store'), ['vbuid' => '99']);

    $created = AdminUser::on('main')->where('uid', 99)->first();
    expect($created)->not->toBeNull()->and($created->aid)->toBe('newstaff')
        ->and(str_starts_with((string) $created->password, '$2y$'))->toBeTrue();
    $response->assertRedirect(route('admin.permissions.edit', $created));
    expect(DB::table('model_has_permissions')->where('model_id', $created->id)->count())->toBe(0);
});

it('refuses an admin whose new id already carries grants: rolled back, grants untouched, controlled error', function (string $kind) {
    $this->actingAs(agsOperator(), 'admin');
    agsForumUser(99, 'newstaff');
    $nextId = agsNextAdminId();
    if ($kind === 'permission') {
        agsPlantGrant($nextId);
    } else {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => AdminUser::class, 'model_id' => $nextId]);
    }
    $before = [DB::table('model_has_permissions')->count(), DB::table('model_has_roles')->count()];
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.' '.json_encode($e->context);
    });

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'admin:audit-grants'));

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeFalse()
        ->and(DB::connection('main')->table('nuke_authors')->count())->toBe(1)
        ->and([DB::table('model_has_permissions')->count(), DB::table('model_has_roles')->count()])->toBe($before)
        ->and(implode("\n", $logged))->toContain('grants already exist under the new admin id')
        ->and(implode("\n", $logged))->not->toContain('newstaff')->not->toContain('@example.com');
})->with(['permission', 'role']);

it('fails closed when the grant check cannot run: nothing is saved', function () {
    $this->actingAs(agsOperator(), 'admin');
    agsForumUser(99, 'newstaff');
    // The grant tables cannot be read (e.g. the default database is down) — simulated at the inspector,
    // since dropping the real tables would also break the operator's own authorization.
    app()->instance(AdminGrantInspector::class, new class extends AdminGrantInspector
    {
        public function grantCountsFor(int $adminId): array
        {
            throw new RuntimeException('grant tables unavailable');
        }
    });

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect()->assertSessionHas('error');

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeFalse();
});

it('refuses a duplicate username (database comparison) and keeps the uid check', function () {
    $this->actingAs(agsOperator(), 'admin');
    agsForumUser(99, 'taken');
    agsForumUser(100, 'fresh');
    AdminUser::on('main')->create(['uid' => 500, 'aid' => 'taken', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 100, 'aid' => 'other-name', 'password' => 'x']);

    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertSessionHas('error', 'يوجد مشرف آخر بنفس اسم المستخدم، لا يمكن إضافة هذا العضو');
    $this->post(route('admin.staff.store'), ['vbuid' => '100'])->assertSessionHas('error', 'هذا العضو متواجد بالفعل في قائمة المشرفين');

    expect(AdminUser::on('main')->where('aid', 'taken')->count())->toBe(1)
        ->and(AdminUser::on('main')->where('uid', 100)->count())->toBe(1);
});

it('serialises concurrent creation: while another creation holds the lock, the request is refused and saves nothing', function () {
    $this->actingAs(agsOperator(), 'admin');
    agsForumUser(99, 'newstaff');
    $held = Cache::lock(AdminStaffController::CREATE_LOCK, 30);
    expect($held->get())->toBeTrue();

    try {
        $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertSessionHas('error', fn ($m) => str_contains($m, 'قيد التنفيذ'));
    } finally {
        $held->release();
    }

    expect(AdminUser::on('main')->where('uid', 99)->exists())->toBeFalse();
    $this->post(route('admin.staff.store'), ['vbuid' => '99'])->assertRedirect();
    expect(AdminUser::on('main')->where('uid', 99)->count())->toBe(1);
});

it('keeps existing admin login and permission-gated access unchanged', function () {
    $admin = AdminUser::on('main')->create(['uid' => 7, 'aid' => 'boss', 'password' => password_hash('s3cret', PASSWORD_BCRYPT)]);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'authors.liststuff', 'guard_name' => 'admin']));

    $this->post('/admincp/login', ['aid' => 'boss', 'password' => 's3cret'])->assertRedirect(route('admin.entry'));
    $this->get(route('admin.staff.index'))->assertOk()->assertSee('boss');
});

// ================================================================ audit

it('reports clean data with exit 0', function () {
    AdminUser::on('main')->create(['uid' => 7, 'aid' => 'boss', 'password' => 'x'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'khotab.edititem', 'guard_name' => 'admin']));

    [$code, $out] = agsAudit();

    expect($code)->toBe(0)->and($out)->toContain('Confirmed findings:')->toContain('none')
        ->toContain('cannot be detected')->toContain('Exit code: 0');
});

it('reports orphan admin roles and direct permissions (exit 1) without touching them', function () {
    agsPlantGrant(4242);
    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => AdminUser::class, 'model_id' => 4343]);

    [$code, $out] = agsAudit(['--json' => true]);
    $report = json_decode($out, true);

    expect($code)->toBe(1)->and($report['status'])->toBe('findings')
        ->and($report['confirmed']['orphan_admin_permissions'][0]['model_id'])->toBe(4242)
        ->and($report['confirmed']['orphan_admin_roles'][0])->toMatchArray(['model_id' => 4343, 'name' => 'super-admin', 'guard' => 'admin'])
        ->and(DB::table('model_has_permissions')->where('model_id', 4242)->count())->toBe(1)
        ->and(DB::table('model_has_roles')->where('model_id', 4343)->count())->toBe(1);
});

it('reports unexpected model types', function () {
    $p = Permission::firstOrCreate(['name' => 'khotab.edititem', 'guard_name' => 'admin']);
    DB::table('model_has_permissions')->insert(['permission_id' => $p->id, 'model_type' => 'App\\Models\\Stranger', 'model_id' => 1]);

    [$code, $out] = agsAudit(['--json' => true]);

    expect($code)->toBe(1)->and(json_decode($out, true)['confirmed']['unexpected_model_types'][0])
        ->toMatchArray(['model_type' => 'App\\Models\\Stranger', 'table' => 'model_has_permissions']);
});

it('reports duplicate usernames and duplicate non-zero forum uids', function () {
    AdminUser::on('main')->create(['uid' => 5, 'aid' => 'twin', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 6, 'aid' => 'twin', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 9, 'aid' => 'a', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 9, 'aid' => 'b', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 0, 'aid' => 'c', 'password' => 'x']);
    AdminUser::on('main')->create(['uid' => 0, 'aid' => 'd', 'password' => 'x']);

    [$code, $out] = agsAudit(['--json' => true]);
    $report = json_decode($out, true);

    expect($code)->toBe(1)
        ->and($report['confirmed']['duplicate_usernames'])->toBe([['aid' => 'twin', 'count' => 2]])
        ->and($report['confirmed']['duplicate_forum_uids'])->toBe([['uid' => 9, 'count' => 2]]);
});

it('lists super-admin holders, radminsuper drift and weak (uid = 0) identities as informational only', function () {
    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
    AdminUser::on('main')->create(['uid' => 7, 'aid' => 'boss', 'password' => 'x', 'radminsuper' => 1])->assignRole($role);
    AdminUser::on('main')->create(['uid' => 8, 'aid' => 'legacy-super', 'password' => 'x', 'radminsuper' => 1]);
    AdminUser::on('main')->create(['uid' => 0, 'aid' => 'old-admin', 'password' => 'x'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'khotab.edititem', 'guard_name' => 'admin']));

    [$code, $out] = agsAudit(['--json' => true]);
    $report = json_decode($out, true);

    expect($code)->toBe(0)
        ->and(array_column($report['informational']['super_admin_holders'], 'aid'))->toBe(['boss'])
        ->and($report['informational']['radminsuper_role_drift'][0])->toMatchArray(['aid' => 'legacy-super', 'super_admin_role' => false])
        ->and(array_column($report['informational']['weak_identity_uid_zero'], 'aid'))->toBe(['old-admin']);

    [, $human] = agsAudit();
    expect($human)->toContain('super_admin_holders: 1')->toContain('boss');
});

it('reports forum-guard grants on missing forum users, and incomplete verification when the forum database is unreachable', function () {
    $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'vbulletin']);
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => VbUser::class, 'model_id' => 16715]);

    [$code, $out] = agsAudit(['--json' => true]);
    expect($code)->toBe(1)->and(json_decode($out, true)['confirmed']['forum_grants_missing_user'])->toBe([['model_id' => 16715]]);

    DB::connection('vbulletin')->table('user')->insert(['userid' => 16715, 'username' => 'forum-boss']);
    expect(agsAudit()[0])->toBe(0);

    config(['database.connections.vbulletin' => ['driver' => 'sqlite', 'database' => '/nonexistent-dir/forum.sqlite', 'prefix' => '']]);
    DB::purge('vbulletin');
    [$code, $out] = agsAudit(['--json' => true]);
    $report = json_decode($out, true);

    expect($code)->toBe(2)->and($report['status'])->toBe('incomplete')
        ->and($report['incomplete'][0])->toContain('forum users')->not->toContain('nonexistent-dir');
});

it('executes no write statements on any connection and exposes no credentials', function () {
    AdminUser::on('main')->create(['uid' => 7, 'aid' => 'boss', 'password' => '$2y$10$secrethashvalue', 'email' => 'boss@example.com', 'API' => 'api-key-123']);
    agsPlantGrant(4242);
    $statements = [];
    DB::listen(function (QueryExecuted $q) use (&$statements) {
        $statements[] = $q->sql;
    });

    [, $json] = agsAudit(['--json' => true]);
    [, $human] = agsAudit();

    $writes = array_filter($statements, fn ($sql) => preg_match('/^\s*(insert|update|delete|replace|create|drop|alter|truncate)\b/i', $sql));
    expect($statements)->not->toBeEmpty()->and($writes)->toBe([])
        ->and($json.$human)->not->toContain('secrethashvalue')->not->toContain('boss@example.com')->not->toContain('api-key-123');
});

it('returns 64 for a usage error', function () {
    $code = Artisan::call('admin:audit-grants', ['--bogus' => true]);

    expect($code)->toBe(64);
});
