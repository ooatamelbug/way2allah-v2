<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CategoryAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 3 — audit. Every real change writes one
 * `admin_audit_log` row through the Phase 0 AdminAuditRecorder, inside the
 * same `main` transaction as the content; legacy `nuke_islamic_logs`
 * compatibility stays default-OFF.
 */
beforeEach(function () {
    H::setUp();
    H::categories(3, 9);
    $this->admin = H::admin(['khotab.addcat', 'anasheed.addcat'], 'cat-auditor');
    $this->actingAs($this->admin, 'admin');
});

it('records the item change and each parent repair with actor, ip and before/after', function () {
    $series = H::series(['cat' => '3|']);
    $item = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('admin.categories.khotab.item.add', $item), ['category_id' => 9]);

    $rows = H::auditRows();
    expect($rows)->toHaveCount(2);

    expect($rows[0]->action)->toBe('add_cat_item')
        ->and($rows[0]->module)->toBe('khotab')
        ->and($rows[0]->entity_type)->toBe('item')
        ->and((int) $rows[0]->entity_id)->toBe($item)
        ->and((int) $rows[0]->actor_admin_id)->toBe((int) $this->admin->id)
        ->and($rows[0]->actor_aid)->toBe('cat-auditor')
        ->and($rows[0]->ip)->toBe('203.0.113.7')
        ->and(json_decode($rows[0]->metadata, true))->toBe(['category_id' => 9, 'cat_before' => '3|', 'cat_after' => '|3|9|']);

    expect($rows[1]->action)->toBe('repair_cat_series')
        ->and($rows[1]->entity_type)->toBe('series')
        ->and((int) $rows[1]->entity_id)->toBe($series)
        ->and(json_decode($rows[1]->metadata, true))->toBe(['cat_before' => '3|', 'cat_after' => '|3|9|']);
});

it('records del_cat_item on remove', function () {
    $item = H::khotabItem(['cat' => '3|9|']);

    $this->post(route('admin.categories.khotab.item.remove', $item), ['category_id' => 9]);

    expect(H::auditRows()[0]->action)->toBe('del_cat_item')
        ->and(json_decode(H::auditRows()[0]->metadata, true)['cat_after'])->toBe('|3|');
});

it('writes nothing to the legacy nuke_islamic_logs while compatibility is default-OFF', function () {
    expect(config('admin_audit.legacy_compatibility_writes'))->toBeFalse();
    $series = H::series(['cat' => '3|']);
    $item = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    $nasheed = H::anasheedItem(['cat_id' => '3|']);

    $this->post(route('admin.categories.khotab.item.add', $item), ['category_id' => 9]);
    $this->post(route('admin.categories.anasheed.item.add', $nasheed), ['category_id' => 9]);
    $this->post(route('admin.categories.khotab.series.remove-all', $series), ['category_id' => 9]);

    expect(count(H::auditRows()))->toBeGreaterThan(3)
        ->and(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

it('rolls back the content and counter when the audit write fails', function () {
    $item = H::khotabItem(['cat' => '3|']);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
    $before = H::snapshot();

    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('admin.categories.khotab.item.add', $item), ['category_id' => 9]))
        ->toThrow(Exception::class, 'audit-down');

    expect(H::snapshot())->toBe($before)->and(H::cat('nuke_islamic_khotab', $item))->toBe('3|');
});

it('rolls back the item and its audit row when a parent repair fails', function () {
    $series = H::series(['cat' => '3|']);
    $item = H::khotabItem(['cat' => '3|', 'ser_id' => $series]);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_series BEFORE UPDATE ON nuke_islamic_series BEGIN SELECT RAISE(ABORT, 'repair-down'); END");
    $before = H::snapshot();

    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('admin.categories.khotab.item.add', $item), ['category_id' => 9]))
        ->toThrow(Exception::class, 'repair-down');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('stores only structured scalar metadata — no rendered HTML, no titles', function () {
    $item = H::khotabItem(['title' => '<b>درس</b>', 'cat' => '3|']);

    $this->post(route('admin.categories.khotab.item.add', $item), ['category_id' => 9]);

    $metadata = H::auditRows()[0]->metadata;
    expect($metadata)->not->toContain('<')->not->toContain('درس')->not->toContain('تصنيف');
});
