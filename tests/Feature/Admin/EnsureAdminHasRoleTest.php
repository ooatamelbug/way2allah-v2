<?php

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class); // Spatie's own tables live on the default connection.

/**
 * Uses the same MainSchema::nukeAuthors() fixture as
 * tests/Feature/Identity/AdminGuardTest.php — not a coincidentally-similar
 * copy. Eloquent caches which columns are "guardable" per table name
 * statically (isGuardableColumn()); two test files faking the same table
 * with different column sets meant whichever ran first silently poisoned
 * mass-assignment for the other (found by actually running both files
 * together, not by inspection). A single named fixture, not just a shared
 * mechanism, is what makes this structurally impossible to reintroduce.
 */
function useInMemoryMainConnectionForAdminMiddleware(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAdminMiddleware();

    // 'web' group is required so the request has a session store to work
    // with — AdminGuard reads/writes admin_auth_id via $request->session().
    Route::middleware(['web', 'admin.role'])->get('/test-admin-only', fn () => 'admin-ok');
    Route::middleware(['web', 'admin.role:super-admin'])->get('/test-super-admin-only', fn () => 'super-admin-ok');
});

it('rejects a request with no authenticated admin at all', function () {
    $this->get('/test-admin-only')->assertStatus(401);
});

it('allows any authenticated admin through a role-agnostic gate', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'plain-admin', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get('/test-admin-only')->assertOk()->assertSee('admin-ok');
});

it('rejects an authenticated admin who lacks the required role', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'plain-admin', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get('/test-super-admin-only')->assertStatus(403);
});

it('allows an authenticated admin who holds the required role', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'boss-admin', 'password' => 'x']);
    $admin->assignRole(\App\Support\Permission\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $this->get('/test-super-admin-only')->assertOk()->assertSee('super-admin-ok');
});
