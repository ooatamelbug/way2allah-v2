<?php

use App\Domain\Admin\Actions\Cds\CdBannerFiles;
use App\Domain\Admin\Actions\Cds\CdGroupIconFiles;
use App\Domain\Admin\Actions\Cds\CreateCdReleaseAction;
use App\Domain\Admin\Actions\Cds\DeleteCdGroupAction;
use App\Domain\Admin\Actions\Cds\DeleteCdReleaseAction;
use App\Domain\Admin\Actions\Cds\InsertCdSubGroupAction;
use App\Domain\Admin\Actions\Cds\ReorderCdReleasesAction;
use App\Domain\Admin\Actions\Cds\UpdateCdGroupAction;
use App\Domain\Admin\Actions\Cds\UpdateCdReleaseAction;
use App\Domain\Admin\Support\PermissionLabels;
use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Models\W2acdItem;
use App\Support\Permission\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\CdAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 6 — authorization (`w2acd.managecds`, one
 * module-level gate as OLD) and authoritative audit for CD administration.
 */
beforeEach(function () {
    $this->root = H::setUp();
    $this->release = H::release();
    $this->group = H::group();
});

afterEach(function () {
    H::tearDown($this->root);
});

/** @return list<array{0: string, 1: string, 2: bool|'group'}> */
function cdRoutes(): array
{
    return [
        ['get', 'admin.cds.index', false],
        ['get', 'admin.cds.create', false],
        ['post', 'admin.cds.store', false],
        ['post', 'admin.cds.order', false],
        ['get', 'admin.cds.edit', true],
        ['put', 'admin.cds.update', true],
        // W2ACD public admin parity, Phase 1 (decision-log #74) — the CD page's banner controls.
        ['get', 'admin.cds.banner', true],
        ['post', 'admin.cds.banner.upload', true],
        ['get', 'admin.cds.banner.delete', true],
        ['delete', 'admin.cds.banner.destroy', true],
        // W2ACD public admin parity, Phase 2 (decision-log #75) — the group page's controls.
        ['get', 'admin.cds.groups.icon', 'group'],
        ['post', 'admin.cds.groups.icon.store', 'group'],
        ['get', 'admin.cds.groups.edit', 'group'],
        ['put', 'admin.cds.groups.update', 'group'],
        ['get', 'admin.cds.groups.subgroups.create', 'group'],
        ['post', 'admin.cds.groups.subgroups.store', 'group'],
        ['get', 'admin.cds.groups.delete', 'group'],
        ['delete', 'admin.cds.groups.destroy', 'group'],
        // W2ACD public admin parity, Phase 3 — M4 on cds-main: a top-level group.
        ['get', 'admin.cds.groups.create', false],
        ['post', 'admin.cds.groups.store', false],
        ['delete', 'admin.cds.destroy', true],
    ];
}

function hitCdRoute(array $route)
{
    [$method, $name, $needsId] = $route;
    $url = route($name, $needsId === 'group' ? ['cdGroup' => test()->group] : ($needsId ? [test()->release] : []));

    return test()->{$method}($url, ['title' => 'X', 'links' => [['title' => 'أ', 'url' => 'https://a']], 'position' => [test()->release => 1]]);
}

it('covers all twenty-one registered cd routes', function () {
    $registered = collect(app('router')->getRoutes()->getRoutesByName())->keys()
        ->filter(fn ($n) => str_starts_with($n, 'admin.cds.'))->sort()->values()->all();

    expect(collect(cdRoutes())->pluck(1)->sort()->values()->all())->toBe($registered)->and($registered)->toHaveCount(21);
});

it('forbids every route without w2acd.managecds — other content permissions grant nothing', function () {
    $before = H::snapshot();
    $this->actingAs(H::admin(['khotab.addadv', 'anasheed.addmirror', 'polls.managepolls']), 'admin');

    foreach (cdRoutes() as $route) {
        hitCdRoute($route)->assertForbidden();
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([])->and(glob($this->root.'/*'))->toBe([]);
});

it('opens every route with w2acd.managecds, and to a super-admin', function (bool $super) {
    $this->actingAs($super ? H::superAdmin() : H::admin(['w2acd.managecds']), 'admin');

    foreach (cdRoutes() as $route) {
        expect(hitCdRoute($route)->status())->not->toBe(403);
    }
    expect(H::row($this->release))->toBeNull();   // the last route deleted it
})->with([false, true]);

it('re-checks the permission inside every action', function () {
    $outsider = H::admin(['khotab.addadv']);
    $item = W2acdItem::query()->findOrFail($this->release);
    $links = [['title' => 'أ', 'url' => 'https://a']];
    $before = H::snapshot();

    expect(fn () => app(CreateCdReleaseAction::class)->execute($outsider, 'X', 0, $links, [UploadedFile::fake()->image('a.png')]))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateCdReleaseAction::class)->execute($outsider, $item, 'X', 0, $links, [], []))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteCdReleaseAction::class)->execute($outsider, $item))->toThrow(AuthorizationException::class);
    expect(fn () => app(ReorderCdReleasesAction::class)->execute($outsider, 0, [$this->release]))->toThrow(AuthorizationException::class);
    expect(fn () => app(CdBannerFiles::class)->upload($outsider, $item, UploadedFile::fake()->image('b.png', 20, 20)))->toThrow(AuthorizationException::class);
    expect(fn () => app(CdBannerFiles::class)->delete($outsider, $item))->toThrow(AuthorizationException::class);
    $group = W2acdGroup::query()->findOrFail($this->group);
    expect(fn () => app(CdGroupIconFiles::class)->upload($outsider, $group, UploadedFile::fake()->image('i.jpg', 20, 20)))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateCdGroupAction::class)->execute($outsider, $group, ['title' => 'X', 'parent_id' => 0]))->toThrow(AuthorizationException::class);
    expect(fn () => app(InsertCdSubGroupAction::class)->execute($outsider, $group, ['Title' => 'X']))->toThrow(AuthorizationException::class);
    expect(fn () => app(InsertCdSubGroupAction::class)->executeRoot($outsider, ['Title' => 'X']))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteCdGroupAction::class)->execute($outsider, $group))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([])->and(glob($this->root.'/*'))->toBe([]);
});

it('shows the sidebar entry to w2acd.managecds holders only', function () {
    $link = 'href="'.route('admin.cds.index').'"';
    $this->actingAs(H::admin(['w2acd.managecds']), 'admin');
    $this->get(route('admin.cds.index'))->assertOk()->assertSee($link, false);

    $this->actingAs(H::admin(['khotab.addadv'], 'cd-other'), 'admin');
    $this->get(route('admin.advfiles.lookup'))->assertOk()->assertDontSee($link, false);
});

it('mints exactly one w2acd permission, labelled from OLD, granted to no one', function () {
    expect(Permission::where('name', 'like', 'w2acd.%')->pluck('name')->all())->toBe(['w2acd.managecds'])
        ->and(PermissionLabels::permissionLabel('w2acd.managecds'))->toBe('الاسطوانات الدعوية')
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});

// ---- audit ----

it('audits add, edit, order and delete with structured before/after', function () {
    $admin = H::superAdmin('cd-auditor');
    $this->actingAs($admin, 'admin');
    $group = H::group(1);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])->post(route('admin.cds.store'), [
        'title' => 'جديدة', 'group_id' => $group, 'links' => [['title' => 'أ', 'url' => 'https://a']],
    ]);
    $new = (int) DB::connection('main')->table('nuke_w2acd_w2acd')->where('title', 'جديدة')->value('id');
    $this->put(route('admin.cds.update', $this->release), [
        'title' => 'إسطوانة', 'links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/a.iso']],
        'existing' => [['name' => '01600000000.png', 'keep' => 1], ['name' => '11600000000.png', 'keep' => 1]],
    ]);
    $this->post(route('admin.cds.order', ['group' => 0]), ['position' => [$this->release => 1]]);
    $this->delete(route('admin.cds.destroy', $new));

    $rows = H::auditRows();
    expect(collect($rows)->map(fn ($r) => [$r->action, $r->module, $r->entity_type])->all())->toBe([
        ['add_cd', 'w2acd', 'cd_release'], ['edit_cd', 'w2acd', 'cd_release'], ['del_cd', 'w2acd', 'cd_release'],
    ])->and([$rows[0]->actor_aid, $rows[0]->ip])->toBe(['cd-auditor', '203.0.113.6']);

    expect(json_decode($rows[0]->metadata, true))->toBe([
        'title' => 'جديدة', 'group_id' => $group, 'links' => [['title' => 'أ', 'url' => 'https://a']], 'images' => [],
        'group_counter' => ['id' => $group, 'before' => 1, 'after' => 2],
    ]);
    expect(json_decode($rows[1]->metadata, true))->toBe([
        'before' => ['links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/a.iso'], ['title' => 'رابط تورنت', 'url' => 'https://archive.org/a.torrent']]],
        'after' => ['links' => [['title' => 'رابط ISO', 'url' => 'https://archive.org/a.iso']]],
    ]);
    $deleted = json_decode($rows[2]->metadata, true);
    expect($deleted['deleted']['title'])->toBe('جديدة')->and($deleted['group_counter'])->toBe(['id' => $group, 'before' => 2, 'after' => 1]);
});

it('audits order_cd per changed release only, and nothing when the order is unchanged', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $second = H::release(['order_in_group' => 1]);

    $this->post(route('admin.cds.order', ['group' => 0]), ['position' => [$second => 1, $this->release => 2]]);
    $n = count(H::auditRows());
    $this->post(route('admin.cds.order', ['group' => 0]), ['position' => [$second => 1, $this->release => 2]])->assertSessionHas('error');

    expect(collect(H::auditRows())->pluck('action')->unique()->values()->all())->toBe(['order_cd'])
        ->and(count(H::auditRows()))->toBe($n)
        ->and(json_decode(H::auditRows()[0]->metadata, true))->toBe(['group_id' => 0, 'order_in_group_before' => 1, 'order_in_group_after' => 2]);
});

it('records a markup-looking title as a marker so the mutation is never blocked', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->post(route('admin.cds.store'), ['title' => '<b>عنوان</b>', 'links' => [['title' => 'أ', 'url' => 'https://a']]])->assertSessionHas('success');

    expect(json_decode(H::auditRows()[0]->metadata, true)['title'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<b>عنوان</b>')]);
});

it('writes nothing to nuke_islamic_logs while compatibility is default-OFF', function () {
    expect(config('admin_audit.legacy_compatibility_writes'))->toBeFalse();
    $this->actingAs(H::superAdmin(), 'admin');

    $this->post(route('admin.cds.store'), ['title' => 'س', 'links' => [['title' => 'أ', 'url' => 'https://a']]]);
    $this->delete(route('admin.cds.destroy', $this->release));

    expect(H::auditRows())->toHaveCount(2)->and(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

it('rolls every mutation back, files included, when the audit write fails', function (string $op) {
    $this->actingAs(H::superAdmin(), 'admin');
    $second = H::release();
    DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    $call = match ($op) {
        'create' => fn () => $this->post(route('admin.cds.store'), ['title' => 'س', 'links' => [['title' => 'أ', 'url' => 'https://a']], 'images' => [UploadedFile::fake()->image('a.png')]]),
        'edit' => fn () => $this->put(route('admin.cds.update', $this->release), ['title' => 'جديد', 'links' => [['title' => 'أ', 'url' => 'https://a']], 'images' => [UploadedFile::fake()->image('b.png')]]),
        'delete' => fn () => $this->delete(route('admin.cds.destroy', $this->release)),
        'order' => fn () => $this->post(route('admin.cds.order', ['group' => 0]), ['position' => [$second => 1, $this->release => 2]]),
    };
    expect($call)->toThrow(Exception::class, 'audit-down');

    expect(H::snapshot())->toBe($before)->and(glob($this->root.'/*'))->toBe([]);
})->with(['create', 'edit', 'delete', 'order']);

it('rolls a group change back when the counter update fails', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $from = H::group(1);
    $to = H::group(1);
    $id = H::release(['group_id' => $from]);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_group BEFORE UPDATE ON nuke_w2acd_groups WHEN NEW.id = {$to} BEGIN SELECT RAISE(ABORT, 'group-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    expect(fn () => $this->put(route('admin.cds.update', $id), ['title' => 'إسطوانة', 'group_id' => $to, 'links' => [['title' => 'أ', 'url' => 'https://a']]]))
        ->toThrow(Exception::class, 'group-down');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('decides edits from the locked row: a stale model cannot resurrect a dropped image', function () {
    $admin = H::superAdmin();
    $stale = W2acdItem::query()->findOrFail($this->release);   // loaded with two images
    DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->release)->update(['thumbnail' => '01600000000.png,']);

    app(UpdateCdReleaseAction::class)->execute($admin, $stale, 'إسطوانة', 0, [['title' => 'أ', 'url' => 'https://a']], [
        ['name' => '01600000000.png', 'keep' => true, 'file' => null],
        ['name' => '11600000000.png', 'keep' => true, 'file' => null],
    ], []);

    expect(H::row($this->release)->thumbnail)->toBe('01600000000.png,');
});
