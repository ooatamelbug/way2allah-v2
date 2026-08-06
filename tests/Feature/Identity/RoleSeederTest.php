<?php

use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class); // Migrates Spatie's own tables onto the default (sqlite :memory:) connection.

function useInMemoryVbulletinConnectionForRoles(): void
{
    InMemoryConnection::setup('vbulletin', [
        'user' => VbulletinSchema::user(),
    ]);
}

beforeEach(function () {
    useInMemoryVbulletinConnectionForRoles();

    DB::connection('vbulletin')->table('user')->insert([
        ['userid' => 1, 'password' => 'x'],
        ['userid' => 16715, 'password' => 'x'],
        ['userid' => 999, 'password' => 'x'],
    ]);
});

it('assigns the super-admin role to both legacy $SuperAdmins ids', function () {
    (new RoleSeeder)->run();

    $superAdmin1 = VbUser::on('vbulletin')->find(1);
    $superAdmin2 = VbUser::on('vbulletin')->find(16715);
    $ordinaryUser = VbUser::on('vbulletin')->find(999);

    expect($superAdmin1->hasRole('super-admin'))->toBeTrue()
        ->and($superAdmin2->hasRole('super-admin'))->toBeTrue()
        ->and($ordinaryUser->hasRole('super-admin'))->toBeFalse();
});

it('scopes vbulletin and admin roles to separate guards', function () {
    (new RoleSeeder)->run();

    expect(Role::where('guard_name', 'vbulletin')->pluck('name')->all())
        ->toBe(['super-admin'])
        ->and(Role::where('guard_name', 'admin')->pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'super-admin']);
});

it('resolves a protected ability through the Gate for a role-holding user, denying everyone else', function () {
    (new RoleSeeder)->run();

    $permission = Permission::firstOrCreate(['name' => 'access-admin-tools', 'guard_name' => 'vbulletin']);
    Role::where('name', 'super-admin')->where('guard_name', 'vbulletin')->first()
        ->givePermissionTo($permission);

    $superAdmin = VbUser::on('vbulletin')->find(1);
    $ordinaryUser = VbUser::on('vbulletin')->find(999);

    expect(Gate::forUser($superAdmin)->allows('access-admin-tools'))->toBeTrue()
        ->and(Gate::forUser($ordinaryUser)->allows('access-admin-tools'))->toBeFalse();
});
