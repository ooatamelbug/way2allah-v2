<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Uploader;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\Fixtures\VbulletinSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 5.9. */
function useInMemoryConnectionsForUploaders(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_uploaders' => MainSchema::nukeUploaders(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
    ]);
    InMemoryConnection::setup('vbulletin', ['user' => VbulletinSchema::user()]);
}

function actingAsAdminWithUploadersPermission(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'khotab.uploaders', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryConnectionsForUploaders();
});

it('lists uploaders', function () {
    actingAsAdminWithUploadersPermission();
    Uploader::create(['uid' => 1, 'username' => 'ahmed', 'email' => 'a@example.com', 'counter' => 5]);

    $this->get(route('admin.uploaders.index'))->assertOk()->assertSee('ahmed');
});

// ---- Global Authenticated Design/CSS Parity (2026-08-23) ----

it('the index page uses real Metronic btn-success classes, matching khotab/uploaders.php\'s real <a class="btn btn-success"> controls', function () {
    actingAsAdminWithUploadersPermission();

    $content = $this->get(route('admin.uploaders.index'))->assertOk()->getContent();

    expect(substr_count($content, 'class="btn btn-success"'))->toBe(2);
});

it('recomputes counter/last_upload from nuke_islamic_khotab, matching uploader.php:12-20', function () {
    actingAsAdminWithUploadersPermission();
    $uploader = Uploader::create(['uid' => 1, 'username' => 'ahmed', 'email' => 'ahmed@example.com', 'counter' => 0]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'uploader' => 'ahmed@example.com', 'addeddate' => 1000],
        ['id' => 2, 'uploader' => 'ahmed@example.com', 'addeddate' => 2000],
    ]);

    $this->post(route('admin.uploaders.recompute'))->assertRedirect();

    expect($uploader->fresh()->counter)->toBe(2)->and($uploader->fresh()->last_upload)->toBe(2000);
});

it('backfills uid/username from vBulletin by email match, matching uploaders.php:19-29', function () {
    actingAsAdminWithUploadersPermission();
    $uploader = Uploader::create(['uid' => 0, 'email' => 'ahmed@example.com', 'counter' => 0]);
    DB::connection('vbulletin')->table('user')->insert(['userid' => 42, 'username' => 'ahmed_vb', 'email' => 'ahmed@example.com']);

    $this->post(route('admin.uploaders.vblink'))->assertRedirect();

    expect($uploader->fresh()->uid)->toBe(42)->and($uploader->fresh()->username)->toBe('ahmed_vb');
});

it('rejects an admin without khotab.uploaders', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.uploaders.index'))->assertForbidden();
});
