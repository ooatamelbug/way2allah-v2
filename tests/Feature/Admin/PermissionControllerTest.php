<?php

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Permission;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * Roadmap task 5.3. Replaces `admincp/authors/edit_author.php` (the one
 * working copy of 5) and fixes the confirmed weak-password-write finding (password reset always wrote plain
 * MD5, the only password-set path anywhere in `admincp/`, confirmed by
 * `admincp.md` §8).
 */
function useInMemoryMainConnectionForPermissionController(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

function actingAsSuperAdminForPermissions(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryMainConnectionForPermissionController();
});

it('rejects a plain (non-super-admin) admin from the permission editor', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'plain', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $this->get(route('admin.permissions.edit', $target))->assertForbidden();
});

it('renders the permission grid grouped by module, checkbox state reflecting real assigned permissions', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    Permission::firstOrCreate(['name' => 'chat.listrooms', 'guard_name' => 'admin']);

    $content = $this->get(route('admin.permissions.edit', $target))->assertOk()->getContent();

    // The assigned permission's checkbox is checked; an unassigned one is not.
    expect($content)
        ->toMatch('/name="permissions\[survey\.modsurvey\]"[^>]*checked/')
        ->not->toMatch('/name="permissions\[chat\.listrooms\]"[^>]*checked/');
});

it('updates an admin\'s permissions via syncPermissions — unchecked permissions are removed, not just additive', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);
    $target->givePermissionTo(Permission::firstOrCreate(['name' => 'survey.modsurvey', 'guard_name' => 'admin']));
    Permission::firstOrCreate(['name' => 'chat.listrooms', 'guard_name' => 'admin']);

    $this->put(route('admin.permissions.update', $target), [
        'permissions' => ['chat.listrooms' => '1'],
    ])->assertRedirect();

    $target->refresh();
    expect($target->hasPermissionTo('chat.listrooms', 'admin'))->toBeTrue()
        ->and($target->hasPermissionTo('survey.modsurvey', 'admin'))->toBeFalse();
});

it('Blueprint §16 item 4 fix: password reset writes a real bcrypt hash, not MD5', function () {
    actingAsSuperAdminForPermissions();
    $target = AdminUser::on('main')->create(['aid' => 'target', 'password' => 'x']);

    $this->put(route('admin.permissions.password', $target), ['new_password' => 'a-real-password'])->assertRedirect();

    $target->refresh();
    expect(str_starts_with($target->password, '$2y$'))->toBeTrue()
        ->and($target->password)->not->toBe(md5('a-real-password'));
});
