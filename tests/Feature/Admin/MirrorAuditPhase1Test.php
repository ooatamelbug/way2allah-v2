<?php

use App\Domain\Admin\Actions\Mirrors\DeleteAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\DeleteKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\MoveKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\UpdateAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\UpdateKhotabMirrorAction;
use App\Domain\Admin\Support\LegacyAuditLogWriteFailed;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MirrorAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 1 — audit attribution for all seven
 * operations, the transaction that binds mutation + parent counter + audit
 * together, and the optional legacy-log coexistence write.
 */
beforeEach(function () {
    H::setUp();
});

/** @return list<array{action: string, module: string, entity_type: string, entity_id: int}> */
function auditShape(): array
{
    return array_map(fn ($r) => [
        'action' => $r->action,
        'module' => $r->module,
        'entity_type' => $r->entity_type,
        'entity_id' => (int) $r->entity_id,
    ], H::auditRows());
}

// ------------------------------------------------- attribution, all seven

it('audits Khotab insert as a mirror row with the MIRROR id, plus an item row', function () {
    $admin = H::superAdmin();
    $this->actingAs($admin, 'admin');

    // Decoy items so the target item's id cannot coincide with the new
    // mirror's id — on a fresh database both would otherwise be 1, and a
    // mix-up between them would pass unnoticed.
    H::khotabItem();
    H::khotabItem();
    H::khotabItem();
    $item = H::khotabItem();

    $this->post(route('admin.mirrors.khotab.store', $item), ['link' => 'https://cdn.example/a.mp3', 'comment' => 'c', 'vedio' => '1'],
        ['REMOTE_ADDR' => '198.51.100.20']);

    $mirrorId = (int) DB::connection('main')->table('nuke_islamic_mirror')->value('id');

    // OLD logged type='mirror' with the khotab id here. Both ids are
    // deliberately different values in this test so a mix-up is visible.
    expect($mirrorId)->not->toBe($item);
    expect(auditShape())->toBe([
        ['action' => 'add_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $mirrorId],
        ['action' => 'add_mirror', 'module' => 'khotab', 'entity_type' => 'item', 'entity_id' => $item],
    ]);

    $row = H::auditRows()[0];
    $meta = json_decode($row->metadata, true);
    expect((int) $row->actor_admin_id)->toBe((int) $admin->id)
        ->and($row->actor_aid)->toBe($admin->aid)
        ->and($row->ip)->toBe('198.51.100.20')
        ->and($meta['item_id'])->toBe($item)
        ->and($meta['link'])->toBe('https://cdn.example/a.mp3');
});

it('audits Khotab edit as one mirror row with a structured from/to diff', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem();
    $mirror = H::khotabMirror($item, ['link' => 'https://old.example/a.mp3', 'trial' => 3]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://new.example/a.mp3', 'comment' => 'جودة عالية', 'vedio' => '0']);

    expect(auditShape())->toBe([
        ['action' => 'edit_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $mirror],
    ]);

    $changes = json_decode(H::auditRows()[0]->metadata, true)['changes'];
    expect($changes['link'])->toBe(['from' => 'https://old.example/a.mp3', 'to' => 'https://new.example/a.mp3'])
        ->and($changes['trial'])->toBe(['from' => 3, 'to' => 0])
        ->and($changes)->not->toHaveKey('comment');   // unchanged fields are not recorded
});

it('audits Khotab delete with a snapshot of what was removed, plus an item row', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://cdn.example/gone.mp3']);

    $this->delete(route('admin.mirrors.khotab.destroy', $mirror));

    expect(auditShape())->toBe([
        ['action' => 'del_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $mirror],
        ['action' => 'del_mirror', 'module' => 'khotab', 'entity_type' => 'item', 'entity_id' => $item],
    ]);

    $meta = json_decode(H::auditRows()[0]->metadata, true);
    expect($meta['parent_missing'])->toBeFalse()
        ->and($meta['deleted']['link'])->toBe('https://cdn.example/gone.mp3');
});

it('audits an orphan delete with parent_missing and no item row', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $orphan = H::khotabMirror(0);   // khid 0: an item row would be unattributable

    $this->delete(route('admin.mirrors.khotab.destroy', $orphan))->assertRedirect();

    expect(auditShape())->toBe([
        ['action' => 'del_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $orphan],
    ]);
    expect(json_decode(H::auditRows()[0]->metadata, true)['parent_missing'])->toBeTrue();
});

it('audits Khotab re-parent as one move_mirror row recording both items', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $from = H::khotabItem(['mirror' => 1]);
    $to = H::khotabItem();
    $mirror = H::khotabMirror($from);

    $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $to]);

    expect(auditShape())->toBe([
        ['action' => 'move_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $mirror],
    ]);

    $meta = json_decode(H::auditRows()[0]->metadata, true);
    expect($meta)->toMatchArray(['from_item_id' => $from, 'to_item_id' => $to, 'from_parent_missing' => false]);
});

it('audits Anasheed insert and delete with the dual-row convention OLD never had for Anasheed', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::anasheedItem(['mirror' => 0]);

    $this->post(route('admin.mirrors.anasheed.store', $item), ['link' => 'https://cdn.example/n.mp3', 'title' => 't', 'vedio' => '0']);
    $mirrorId = (int) DB::connection('main')->table('nuke_anasheed_mirror')->value('id');
    $this->delete(route('admin.mirrors.anasheed.destroy', $mirrorId));

    expect(auditShape())->toBe([
        ['action' => 'add_mirror', 'module' => 'anasheed', 'entity_type' => 'mirror', 'entity_id' => $mirrorId],
        ['action' => 'add_mirror', 'module' => 'anasheed', 'entity_type' => 'item', 'entity_id' => $item],
        ['action' => 'del_mirror', 'module' => 'anasheed', 'entity_type' => 'mirror', 'entity_id' => $mirrorId],
        ['action' => 'del_mirror', 'module' => 'anasheed', 'entity_type' => 'item', 'entity_id' => $item],
    ]);
});

it('audits Anasheed edit at mirror granularity and records whether the size edit was honoured', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $mirror = H::anasheedMirror(H::anasheedItem(), ['down' => 2, 'linksize' => 10]);

    $this->put(route('admin.mirrors.anasheed.update', $mirror), ['title' => 'new', 'linksize' => '99', 'vedio' => '0']);

    expect(auditShape())->toBe([
        ['action' => 'edit_mirror', 'module' => 'anasheed', 'entity_type' => 'mirror', 'entity_id' => $mirror],
    ]);
    $meta = json_decode(H::auditRows()[0]->metadata, true);
    expect($meta['size_edit_accepted'])->toBeFalse()
        ->and($meta['changes'])->not->toHaveKey('linksize');
});

it('never writes markup into any audit field across all seven operations', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $k = H::khotabItem(['mirror' => 1]);
    $this->post(route('admin.mirrors.khotab.store', $k), ['link' => 'https://cdn.example/1.mp3', 'comment' => 'x', 'vedio' => '1']);
    $km = H::khotabMirror($k);
    $this->put(route('admin.mirrors.khotab.update', $km), ['link' => 'https://cdn.example/2.mp3', 'vedio' => '1']);
    $this->post(route('admin.mirrors.khotab.move', $km), ['target_item_id' => H::khotabItem()]);
    $this->delete(route('admin.mirrors.khotab.destroy', $km));
    $a = H::anasheedItem(['mirror' => 1]);
    $this->post(route('admin.mirrors.anasheed.store', $a), ['link' => 'https://cdn.example/3.mp3', 'vedio' => '0']);
    $am = H::anasheedMirror($a);
    $this->put(route('admin.mirrors.anasheed.update', $am), ['title' => 'y', 'vedio' => '0']);
    $this->delete(route('admin.mirrors.anasheed.destroy', $am));

    // khotab insert 2 + edit 1 + re-parent 1 + delete 2 (mirror + item: the
    // moved mirror's new parent exists) + anasheed insert 2 + edit 1 +
    // delete 2 = 11.
    expect(H::auditRows())->toHaveCount(11);
    foreach (H::auditRows() as $row) {
        expect((string) $row->metadata)->not->toMatch('/<[a-z!\/][^>]*>/i');
    }
});

// --------------------------------------------- one transaction, all or none

/**
 * Each mutation, run through its action directly, with a callback that
 * sets up the data and returns a closure performing the operation plus a
 * snapshot function of every table the operation touches.
 *
 * @return array<string, array{0: Closure}>
 */
function mutationsUnderTest(): array
{
    return [
        'khotab insert' => [function ($admin) {
            $item = KhotabItem::query()->find(H::khotabItem(['mirror' => 2]));

            return fn () => app(InsertKhotabMirrorAction::class)->execute($admin, $item, ['link' => 'https://x.example/r.mp3', 'vedio' => 1]);
        }],
        'khotab edit' => [function ($admin) {
            $mirror = Mirror::query()->find(H::khotabMirror(H::khotabItem(), ['link' => 'https://keep.example/a.mp3', 'trial' => 5]));

            return fn () => app(UpdateKhotabMirrorAction::class)->execute($admin, $mirror, ['link' => 'https://changed.example/a.mp3', 'vedio' => 1]);
        }],
        'khotab delete' => [function ($admin) {
            $mirror = Mirror::query()->find(H::khotabMirror(H::khotabItem(['mirror' => 3])));

            return fn () => app(DeleteKhotabMirrorAction::class)->execute($admin, $mirror);
        }],
        'khotab re-parent' => [function ($admin) {
            $mirror = Mirror::query()->find(H::khotabMirror(H::khotabItem(['mirror' => 2])));
            $target = KhotabItem::query()->find(H::khotabItem(['mirror' => 4]));

            return fn () => app(MoveKhotabMirrorAction::class)->execute($admin, $mirror, $target);
        }],
        'anasheed insert' => [function ($admin) {
            $item = AnasheedItem::query()->find(H::anasheedItem(['mirror' => 2]));

            return fn () => app(InsertAnasheedMirrorAction::class)->execute($admin, $item, ['link' => 'https://x.example/r.mp3', 'vedio' => 0]);
        }],
        'anasheed edit' => [function ($admin) {
            $mirror = AnasheedMirror::query()->find(H::anasheedMirror(H::anasheedItem(), ['title' => 'keep', 'trial' => 5]));

            return fn () => app(UpdateAnasheedMirrorAction::class)->execute($admin, $mirror, ['title' => 'changed', 'vedio' => 0]);
        }],
        'anasheed delete' => [function ($admin) {
            $mirror = AnasheedMirror::query()->find(H::anasheedMirror(H::anasheedItem(['mirror' => 3])));

            return fn () => app(DeleteAnasheedMirrorAction::class)->execute($admin, $mirror);
        }],
    ];
}

function contentSnapshot(): array
{
    $main = DB::connection('main');

    return [
        'khotab_mirrors' => $main->table('nuke_islamic_mirror')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'khotab_items' => $main->table('nuke_islamic_khotab')->orderBy('id')->get(['id', 'mirror', 'lastmirror'])->map(fn ($r) => (array) $r)->all(),
        'anasheed_mirrors' => $main->table('nuke_anasheed_mirror')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'anasheed_items' => $main->table('nuke_anasheed_anasheed')->orderBy('id')->get(['id', 'mirror', 'lastmirror'])->map(fn ($r) => (array) $r)->all(),
    ];
}

it('rolls back the mutation AND the parent counter when the authoritative audit write fails', function (Closure $prepare) {
    $operation = $prepare(H::superAdmin());
    $before = contentSnapshot();

    // Make the authoritative audit insert impossible.
    Schema::connection('main')->drop('admin_audit_log');

    expect($operation)->toThrow(QueryException::class);

    // Every content table and every counter is exactly as it was.
    expect(contentSnapshot())->toBe($before);
})->with(mutationsUnderTest());

it('commits the mutation, the counter and the audit together on success', function (Closure $prepare) {
    $operation = $prepare(H::superAdmin());
    $before = contentSnapshot();

    $operation();

    expect(contentSnapshot())->not->toBe($before)
        ->and(H::auditRows())->not->toBeEmpty();
})->with(mutationsUnderTest());

// ------------------------------------------------ legacy coexistence log

it('writes nothing to nuke_islamic_logs by default', function (Closure $prepare) {
    $prepare(H::superAdmin())();

    expect(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
})->with(mutationsUnderTest());

it('mirrors every authoritative row into nuke_islamic_logs when enabled, with legacy module names and no URL', function () {
    config(['admin_audit.legacy_compatibility_writes' => true]);
    $this->actingAs(H::superAdmin(), 'admin');

    $item = H::khotabItem();
    $this->post(route('admin.mirrors.khotab.store', $item), ['link' => 'https://cdn.example/secret-path.mp3', 'vedio' => '1']);
    $mirrorId = (int) DB::connection('main')->table('nuke_islamic_mirror')->value('id');

    $legacy = DB::connection('main')->table('nuke_islamic_logs')->orderBy('id')->get();

    expect($legacy)->toHaveCount(2)
        ->and($legacy->pluck('module')->unique()->all())->toBe(['Khotab'])
        ->and($legacy->map(fn ($r) => [$r->type, (int) $r->target_id])->all())->toBe([['mirror', $mirrorId], ['item', $item]]);

    foreach ($legacy as $row) {
        expect($row->extrainfo)->not->toContain('http')->not->toContain('<');
    }
});

it('does not let a failed legacy write roll back the mutation or the authoritative audit — and reports it', function () {
    config(['admin_audit.legacy_compatibility_writes' => true]);
    Exceptions::fake();
    Schema::connection('main')->drop('nuke_islamic_logs');

    $admin = H::superAdmin();
    $item = KhotabItem::query()->find(H::khotabItem(['mirror' => 0]));

    $mirror = app(InsertKhotabMirrorAction::class)->execute($admin, $item, ['link' => 'https://cdn.example/ok.mp3', 'vedio' => 1]);

    expect(Mirror::query()->find($mirror->id))->not->toBeNull()
        ->and(H::khotabCounter($item->id))->toBe(1)
        ->and(H::auditRows())->toHaveCount(2);

    Exceptions::assertReported(LegacyAuditLogWriteFailed::class);
});

it('keeps the authoritative trail complete whether or not compatibility is enabled', function (bool $enabled) {
    config(['admin_audit.legacy_compatibility_writes' => $enabled]);

    $admin = H::superAdmin();
    $item = AnasheedItem::query()->find(H::anasheedItem());
    app(InsertAnasheedMirrorAction::class)->execute($admin, $item, ['link' => 'https://cdn.example/a.mp3', 'vedio' => 0]);

    expect(H::auditRows())->toHaveCount(2);
})->with([true, false]);
