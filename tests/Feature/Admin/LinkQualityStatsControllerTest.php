<?php

use App\Domain\Admin\Jobs\RecheckKhotabLinkSizeJob;
use App\Domain\Admin\Jobs\RecheckMirrorLinkSizeJob;
use App\Domain\Admin\Jobs\RecheckTelawahLinkSizeJob;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\LinkSizeChecker;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\TelawahItem;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 6.7. */
function useInMemoryConnectionForLinkQuality(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
    ]);
}

function actingAsAdminWithRepairPermission(string $permission): AdminUser
{
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']));
    test()->actingAs($admin, 'admin');

    return $admin;
}

beforeEach(function () {
    useInMemoryConnectionForLinkQuality();
});

it('mirror: lists only rows whose percent is mismatched, excluding youtube/soundcloud links', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 500, 'percent' => 50, 'time' => 100]);
    Mirror::create(['khid' => 2, 'link' => 'https://example.com/b.mp4', 'linksize' => 1000, 'online' => 1000, 'percent' => 100, 'time' => 100]);
    Mirror::create(['khid' => 3, 'link' => 'https://youtu.be/xyz', 'linksize' => 1000, 'online' => 1, 'percent' => 0, 'time' => 100]);

    $response = $this->get(route('admin.link-quality.mirror.index'));

    $response->assertOk()->assertSee('a.mp4')->assertDontSee('b.mp4')->assertDontSee('youtu.be');
});

it('mirror: recompute recalculates percent from online/linksize', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 500, 'percent' => 0]);

    $this->post(route('admin.link-quality.mirror.recompute'))->assertRedirect();

    expect((int) $mirror->fresh()->percent)->toBe(50);
});

it('mirror: fix-size accepts the current online size as correct, matching stats.php:65', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 700, 'percent' => 70]);

    $this->post(route('admin.link-quality.mirror.fix-size', $mirror))->assertRedirect();

    expect($mirror->fresh()->linksize)->toBe(700)->and((int) $mirror->fresh()->percent)->toBe(100);
});

it('mirror: recheck dispatches RecheckMirrorLinkSizeJob', function () {
    Queue::fake();
    actingAsAdminWithRepairPermission('khotab.repair');
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 0, 'percent' => 0]);

    $this->post(route('admin.link-quality.mirror.recheck', $mirror))->assertRedirect();

    Queue::assertPushed(RecheckMirrorLinkSizeJob::class);
});

it('khotab: lists only rows whose percent is not exactly 100, matching stats_khotab.php:148', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    KhotabItem::create(['title' => 'Mismatched', 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 500, 'percent' => 50, 'time' => 100]);
    KhotabItem::create(['title' => 'Matched', 'link' => 'https://example.com/b.mp4', 'linksize' => 1000, 'online' => 1000, 'percent' => 100, 'time' => 100]);

    $response = $this->get(route('admin.link-quality.khotab.index'));

    $response->assertOk()->assertSee('Mismatched')->assertDontSee('Matched');
});

it('khotab: large-files lists items over 200MB regardless of percent match', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    KhotabItem::create(['title' => 'Big', 'link' => 'https://example.com/big.mp4', 'linksize' => 300_000_000, 'online' => 300_000_000, 'percent' => 100, 'time' => 100]);
    KhotabItem::create(['title' => 'Small', 'link' => 'https://example.com/small.mp4', 'linksize' => 1000, 'online' => 1000, 'percent' => 100, 'time' => 100]);

    $response = $this->get(route('admin.link-quality.khotab.large-files'));

    $response->assertOk()->assertSee('Big')->assertDontSee('Small');
});

it('telawah: lists mismatched rows ordered by mytime, matching telawah/stats.php:139', function () {
    actingAsAdminWithRepairPermission('telawah.repair');
    TelawahItem::create(['title' => 'Mismatched', 'link' => 'https://example.com/a.mp3', 'linksize' => 1000, 'online' => 400, 'percent' => 40, 'mytime' => 100]);
    TelawahItem::create(['title' => 'Matched', 'link' => 'https://example.com/b.mp3', 'linksize' => 1000, 'online' => 1000, 'percent' => 100, 'mytime' => 100]);

    $response = $this->get(route('admin.link-quality.telawah.index'));

    $response->assertOk()->assertSee('Mismatched')->assertDontSee('Matched');
});

it('khotab: recheck dispatches RecheckKhotabLinkSizeJob', function () {
    Queue::fake();
    actingAsAdminWithRepairPermission('khotab.repair');
    $khotabItem = KhotabItem::create(['title' => 'X', 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 0, 'percent' => 0]);

    $this->post(route('admin.link-quality.khotab.recheck', $khotabItem))->assertRedirect();

    Queue::assertPushed(RecheckKhotabLinkSizeJob::class);
});

it('telawah: recheck dispatches RecheckTelawahLinkSizeJob', function () {
    Queue::fake();
    actingAsAdminWithRepairPermission('telawah.repair');
    $telawahItem = TelawahItem::create(['title' => 'X', 'link' => 'https://example.com/a.mp3', 'linksize' => 1000, 'online' => 0, 'percent' => 0]);

    $this->post(route('admin.link-quality.telawah.recheck', $telawahItem))->assertRedirect();

    Queue::assertPushed(RecheckTelawahLinkSizeJob::class);
});

it('mirror and khotab permissions are independent — telawah.repair does not grant khotab.repair', function () {
    actingAsAdminWithRepairPermission('telawah.repair');

    $this->get(route('admin.link-quality.mirror.index'))->assertForbidden();
    $this->get(route('admin.link-quality.khotab.index'))->assertForbidden();
});

it('rejects an admin without khotab.repair', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.link-quality.mirror.index'))->assertForbidden();
});

it('mirror: auto-repairs a row with 0 recorded linksize but a real online size on render, matching stats.php:176-182', function () {
    actingAsAdminWithRepairPermission('khotab.repair');
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 0, 'online' => 600_000, 'percent' => 0, 'time' => 100]);

    $this->get(route('admin.link-quality.mirror.index'))->assertOk();

    expect($mirror->fresh()->linksize)->toBe(600_000)->and((int) $mirror->fresh()->percent)->toBe(100);
});

it('RecheckMirrorLinkSizeJob updates checktime/online/percent from the response Content-Length', function () {
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 200, ['Content-Length' => '1000'])]);
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 1000, 'online' => 0, 'percent' => 0, 'checktime' => 0]);

    (new RecheckMirrorLinkSizeJob($mirror->id))->handle(new LinkSizeChecker());

    $fresh = $mirror->fresh();
    expect($fresh->online)->toBe(1000)->and((int) $fresh->percent)->toBe(100)->and($fresh->checktime)->toBeGreaterThan(0);
});

it('RecheckMirrorLinkSizeJob and RecheckKhotabLinkSizeJob downgrade https to http, matching stats.php:78/stats_khotab.php:74', function () {
    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
        expect($request->url())->toStartWith('http://');

        return \Illuminate\Support\Facades\Http::response('', 200, ['Content-Length' => '500']);
    });
    $mirror = Mirror::create(['khid' => 1, 'link' => 'https://example.com/a.mp4', 'linksize' => 500, 'online' => 0, 'percent' => 0]);
    $khotabItem = KhotabItem::create(['title' => 'X', 'link' => 'https://example.com/b.mp4', 'linksize' => 500, 'online' => 0, 'percent' => 0]);

    (new RecheckMirrorLinkSizeJob($mirror->id))->handle(new LinkSizeChecker());
    (new RecheckKhotabLinkSizeJob($khotabItem->id))->handle(new LinkSizeChecker());
});

it('RecheckTelawahLinkSizeJob does not downgrade https to http, matching the confirmed telawah/stats.php inconsistency', function () {
    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
        expect($request->url())->toStartWith('https://');

        return \Illuminate\Support\Facades\Http::response('', 200, ['Content-Length' => '500']);
    });
    $telawahItem = TelawahItem::create(['title' => 'X', 'link' => 'https://example.com/a.mp3', 'linksize' => 500, 'online' => 0, 'percent' => 0]);

    (new RecheckTelawahLinkSizeJob($telawahItem->id))->handle(new LinkSizeChecker());
});
