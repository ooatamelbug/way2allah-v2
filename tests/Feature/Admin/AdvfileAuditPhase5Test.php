<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdvfileAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 5 — audit: one admin_audit_log row per real
 * transition, in the same transaction; no raw `code` in metadata; legacy
 * nuke_islamic_logs stays default-OFF.
 */
beforeEach(function () {
    H::setUp();
    $this->admin = H::superAdmin('adv-auditor');
    $this->actingAs($this->admin, 'admin');
});

it('records request_adv with actor, ip and the state change', function () {
    $ids = H::item(0);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->post(route('admin.advfiles.request', $ids['item']));

    $row = H::auditRows()[0];
    expect([$row->action, $row->module, $row->entity_type, (int) $row->entity_id, $row->actor_aid, $row->ip])
        ->toBe(['request_adv', 'khotab', 'item', $ids['item'], 'adv-auditor', '203.0.113.5'])
        ->and(json_decode($row->metadata, true))->toBe(['advfile_before' => 0, 'advfile_after' => -1]);
});

it('records add_advfile with a code fingerprint, never the code itself', function () {
    $ids = H::item(-1, [], 5);
    $code = '<object data="promo.swf"></object>';

    $this->post(route('admin.advfiles.approve', $ids['item']), ['credit' => 4, 'code' => $code, 'audio_url' => 'https://a/b.mp3']);

    $row = H::auditRows()[0];
    $meta = json_decode($row->metadata, true);
    expect($row->action)->toBe('add_advfile')
        ->and($row->metadata)->not->toContain('promo.swf')
        ->and($meta['code'])->toBe(['sha256' => hash('sha256', $code), 'length' => mb_strlen($code)])
        ->and(collect($meta)->except('code')->all())->toBe([
            'advfile_before' => -1, 'advfile_after' => 1, 'credit' => 4, 'audio_url' => 'https://a/b.mp3', 'replaced_stale_row' => false,
            'counters' => [
                'author' => ['id' => $ids['author'], 'before' => 5, 'after' => 6],
                'group' => ['id' => $ids['group'], 'before' => 5, 'after' => 6],
                'series' => ['id' => $ids['series'], 'before' => 5, 'after' => 6],
            ],
        ]);
});

it('records edit_advfile with only changed fields', function () {
    $ids = H::item(1);
    H::file($ids['item'], ['credit' => 2, 'code' => 'أ', 'audio_url' => 'u']);

    $this->put(route('admin.advfiles.update', $ids['item']), ['credit' => 3, 'code' => 'أ', 'audio_url' => 'u']);

    expect(json_decode(H::auditRows()[0]->metadata, true))->toBe(['before' => ['credit' => 2], 'after' => ['credit' => 3]]);
});

it('records del_advfile with the removed payload fingerprint and counters', function () {
    $ids = H::item(1, [], 1);
    H::file($ids['item'], ['credit' => 8, 'code' => 'ك', 'audio_url' => 'u', 'small_thumb' => 1]);

    $this->delete(route('admin.advfiles.unpublish', $ids['item']));

    $meta = json_decode(H::auditRows()[0]->metadata, true);
    expect(H::auditRows()[0]->action)->toBe('del_advfile')
        ->and($meta['deleted'])->toBe(['credit' => 8, 'code' => ['sha256' => hash('sha256', 'ك'), 'length' => 1], 'audio_url' => 'u', 'small_thumb' => 1, 'large_thumb' => 0])
        ->and($meta['counters']['author'])->toBe(['id' => $ids['author'], 'before' => 1, 'after' => 0]);
});

it('records reset_advfile and credit_advfile', function () {
    $pending = H::item(-1);
    $published = H::item(1);
    H::file($published['item'], ['credit' => 5]);

    $this->post(route('admin.advfiles.reject', $pending['item']));
    $this->post(route('admin.advfiles.credits.save'), ['credit' => [$published['item'] => 6]]);

    $rows = H::auditRows();
    expect([$rows[0]->action, json_decode($rows[0]->metadata, true)])->toBe(['reset_advfile', ['advfile_before' => -1, 'advfile_after' => 0]])
        ->and([$rows[1]->action, (int) $rows[1]->entity_id, json_decode($rows[1]->metadata, true)])->toBe(['credit_advfile', $published['item'], ['credit_before' => 5, 'credit_after' => 6]]);
});

it('records a markup-looking audio url as a marker so the transition is never blocked', function () {
    $ids = H::item(-1);

    $this->post(route('admin.advfiles.approve', $ids['item']), ['audio_url' => '<a href="x">x</a>'])->assertSessionHas('success');

    expect(json_decode(H::auditRows()[0]->metadata, true)['audio_url'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<a href="x">x</a>')]);
});

it('writes nothing to nuke_islamic_logs while compatibility is default-OFF', function () {
    expect(config('admin_audit.legacy_compatibility_writes'))->toBeFalse();
    $ids = H::item(0);

    $this->post(route('admin.advfiles.request', $ids['item']));
    $this->post(route('admin.advfiles.approve', $ids['item']), ['credit' => 1]);
    $this->delete(route('admin.advfiles.unpublish', $ids['item']));

    expect(H::auditRows())->toHaveCount(3)->and(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

it('rolls every transition back when the audit write fails', function (string $op) {
    $none = H::item(0)['item'];
    $pending = H::item(-1)['item'];
    $published = H::item(1)['item'];
    H::file($published, ['credit' => 5]);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    $call = match ($op) {
        'request' => fn () => $this->post(route('admin.advfiles.request', $none)),
        'approve' => fn () => $this->post(route('admin.advfiles.approve', $pending), ['credit' => 1, 'code' => 'x']),
        'reject' => fn () => $this->post(route('admin.advfiles.reject', $pending)),
        'edit' => fn () => $this->put(route('admin.advfiles.update', $published), ['credit' => 9]),
        'remove' => fn () => $this->delete(route('admin.advfiles.unpublish', $published)),
        'credits' => fn () => $this->post(route('admin.advfiles.credits.save'), ['credit' => [$published => 9]]),
    };
    expect($call)->toThrow(Exception::class, 'audit-down');

    expect(H::snapshot())->toBe($before);
})->with(['request', 'approve', 'reject', 'edit', 'remove', 'credits']);

it('rolls an approval back when a counter update fails after the payload and state were written', function () {
    $ids = H::item(-1, [], 5);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_series BEFORE UPDATE ON nuke_islamic_series BEGIN SELECT RAISE(ABORT, 'series-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('admin.advfiles.approve', $ids['item']), ['credit' => 1]))->toThrow(Exception::class, 'series-down');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});
