<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    InMemoryConnection::setup('main', ['nuke_authors' => MainSchema::nukeAuthors()]);
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
        ->and(\Illuminate\Support\Facades\Hash::check('way2allah', $created->password))->toBeFalse();
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
