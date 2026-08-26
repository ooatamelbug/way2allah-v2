<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Channel;
use App\Support\Permission\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * AdminCP Shared Navbar/Sidebar Final Reconstruction — covers the active/
 * open-state logic in `layouts/admin.blade.php`, including the confirmed
 * bug this task fixed: two sibling children whose routes share the same
 * `Str::beforeLast($route, '.')` prefix (`admin.link-quality.khotab.index`
 * and `admin.link-quality.khotab.large-files` both reduce to
 * `admin.link-quality.khotab`) previously both lit up as active together
 * whenever either was visited.
 */
function useInMemoryMainConnectionForSidebarActiveState(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_survey' => MainSchema::nukeSurvey(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

function actingAsSuperAdminForSidebarActiveState(): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'boss', 'password' => 'x']);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryMainConnectionForSidebarActiveState();
});

it('marks a flat item active on its own page, matching legacy having no siblings to confuse it', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.survey.index'))->assertOk()->getContent();

    expect($content)->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.survey.index'), '/').'">/');
});

it('marks the parent "active open" and only its own child active on a grouped page', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.staff.index'))->assertOk()->getContent();

    expect($content)->toContain('active open')
        ->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.staff.index'), '/').'">/')
        ->not->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.staff.create'), '/').'">/');
});

it('confirmed bug fix: visiting khotab large-files does not also mark the khotab quality-index sibling active', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.link-quality.khotab.large-files'))->assertOk()->getContent();

    expect($content)->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.link-quality.khotab.large-files'), '/').'">/')
        ->not->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.link-quality.khotab.index'), '/').'">/');
});

it('confirmed bug fix: visiting the khotab quality index does not also mark large-files active', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.link-quality.khotab.index'))->assertOk()->getContent();

    expect($content)->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.link-quality.khotab.index'), '/').'">/')
        ->not->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.link-quality.khotab.large-files'), '/').'">/');
});

it('a non-sidebar-listed sub-route (viewing one broadcast channel) still highlights its one listed sibling via the prefix fallback', function () {
    actingAsSuperAdminForSidebarActiveState();
    $channel = Channel::create(['title' => 'Test Channel', 'ch_visits' => 0, 'streamcode' => '<iframe></iframe>']);

    $content = $this->get(route('admin.broadcasting.edit', $channel))->assertOk()->getContent();

    expect($content)->toContain('active open')
        ->toMatch('/<li class="active">\s*<a href="'.preg_quote(route('admin.broadcasting.index'), '/').'">/');
});

it('the dashboard home link is active only on the dashboard itself, not on any module page', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.survey.index'))->assertOk()->getContent();

    expect($content)->toMatch('/<li class="">\s*<a href="'.preg_quote(route('admin.entry'), '/').'">/');
});

it('no retired module (chat) appears anywhere in the rendered sidebar', function () {
    actingAsSuperAdminForSidebarActiveState();

    $content = $this->get(route('admin.entry'))->assertOk()->getContent();

    expect($content)->not->toContain('غرفة الهداية')
        ->not->toContain('admin.chat.');
});
