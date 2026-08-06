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
