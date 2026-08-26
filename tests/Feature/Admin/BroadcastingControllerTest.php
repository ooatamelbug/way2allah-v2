<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Channel;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 5.10 — reuses the existing Channel model directly, no new table fixture. */
function useInMemoryMainConnectionForBroadcasting(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForBroadcasting();
});

// ---- Index (Admin Broadcasting Final Closure, 2026-08-22) ----

it('lists only channels with a non-empty streamcode, ordered by title, matching index.php:36 op=editstream', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $withStream = Channel::create(['title' => 'Zeta', 'ch_visits' => 0, 'streamcode' => '<iframe></iframe>']);
    $withoutStream = Channel::create(['title' => 'Alpha', 'ch_visits' => 0, 'streamcode' => '']);
    $secondWithStream = Channel::create(['title' => 'Beta', 'ch_visits' => 0, 'streamcode' => '<iframe></iframe>']);

    $content = $this->get(route('admin.broadcasting.index'))->assertOk()->getContent();

    expect($content)->toContain(route('admin.broadcasting.edit', $withStream))
        ->toContain(route('admin.broadcasting.edit', $secondWithStream))
        ->not->toContain(route('admin.broadcasting.edit', $withoutStream));
    // Title text is not rendered (legacy index.php:16-49 shows only each
    // channel's real thumbnail image, no visible title) — order is
    // asserted via each channel's own edit URL position instead.
    expect(strpos($content, route('admin.broadcasting.edit', $secondWithStream)))
        ->toBeLessThan(strpos($content, route('admin.broadcasting.edit', $withStream)));
});

it('renders each channel as a real 110x110 thumbnail image, matching index.php:16-49\'s real markup, not a plain text link', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0, 'streamcode' => '<iframe></iframe>']);

    $content = $this->get(route('admin.broadcasting.index'))->assertOk()->getContent();

    expect($content)->toContain('class="attt" style="width:120px;height:120px;float:right"')
        ->toContain('images/channels/'.$channel->id.'.png')
        ->toContain('height="110" width="110"')
        ->not->toContain('Al-Nas');
});

it('shows an empty-state message when no channel has a streamcode', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.broadcasting.index'))->assertOk()->assertSee('لا توجد قنوات لديها كود بث حاليًا.');
});

it('rejects an admin without broadcasting.editstream from the index page', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.broadcasting.index'))->assertForbidden();
});

it('does not resolve any route for the dead addstream/delete_stream legacy operations', function () {
    expect(app('router')->getRoutes()->getByName('admin.broadcasting.addstream'))->toBeNull();
    expect(app('router')->getRoutes()->getByName('admin.broadcasting.delete'))->toBeNull();
});

// ---- Global Authenticated Design/CSS Parity (2026-08-23) ----

it('the edit page uses a real Metronic btn class, matching edit_stream.php\'s real "btn green-haze" button', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');
    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0]);

    $content = $this->get(route('admin.broadcasting.edit', $channel))->assertOk()->getContent();

    expect($content)->toContain('class="btn green-haze"');
});

it('updates a channel\'s streamcode, matching edit_stream.php:42-44', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0]);

    $this->put(route('admin.broadcasting.update', $channel), [
        'streamcode' => '<iframe src="https://stream.example.com/al-nas"></iframe>',
    ])->assertRedirect();

    expect($channel->fresh()->streamcode)->toBe('<iframe src="https://stream.example.com/al-nas"></iframe>');
});

it('renders the current streamcode unescaped', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'broadcasting.editstream', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0, 'streamcode' => '<iframe src="x"></iframe>']);

    $content = $this->get(route('admin.broadcasting.edit', $channel))->assertOk()->getContent();

    expect($content)->toContain('<iframe src="x"></iframe>');
});

it('rejects an admin without broadcasting.editstream', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $channel = Channel::create(['title' => 'Al-Nas', 'ch_visits' => 0]);

    $this->get(route('admin.broadcasting.edit', $channel))->assertForbidden();
});
