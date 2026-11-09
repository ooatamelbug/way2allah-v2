<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PollAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 4 — audit. One admin_audit_log row per real
 * mutation, in the mutation's own transaction; legacy nuke_islamic_logs
 * stays default-OFF.
 */
beforeEach(function () {
    H::setUp();
    $this->admin = H::superAdmin('poll-auditor');
    $this->actingAs($this->admin, 'admin');
});

it('records add_poll with title, language and the non-blank choices', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('admin.polls.store'), ['pollTitle' => 'سؤال', 'options' => [1 => 'أ', 3 => 'ج']]);

    $row = H::auditRows()[0];
    $poll = (int) DB::connection('main')->table('nuke_poll_desc')->value('pollID');
    expect([$row->action, $row->module, $row->entity_type, (int) $row->entity_id, $row->actor_aid, $row->ip])
        ->toBe(['add_poll', 'polls', 'poll', $poll, 'poll-auditor', '203.0.113.9'])
        ->and(json_decode($row->metadata, true))->toBe(['title' => 'سؤال', 'planguage' => 'arabic', 'options' => ['1' => 'أ', '3' => 'ج']]);
});

it('records edit_poll with only the changed fields, before and after', function () {
    $poll = H::poll('قديم', ['أ', 'ب']);

    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'جديد', 'options' => [1 => 'أ', 2 => 'بب']]);

    expect(json_decode(H::auditRows()[0]->metadata, true))->toBe([
        'before' => ['title' => 'قديم', 'options' => ['2' => 'ب']],
        'after' => ['title' => 'جديد', 'options' => ['2' => 'بب']],
    ]);
});

it('records del_poll with a full snapshot, including each choice vote count', function () {
    $poll = H::poll('Q', ['أ', 'ب'], [4, 1], ['timeStamp' => 1600000000, 'comments' => 2]);

    $this->delete(route('admin.polls.destroy', $poll));

    $deleted = json_decode(H::auditRows()[0]->metadata, true)['deleted'];
    expect(collect($deleted)->except('options')->all())->toBe([
        'title' => 'Q', 'planguage' => 'arabic', 'time_stamp' => 1600000000, 'voters' => 5, 'artid' => 0, 'comments' => 2,
    ])->and($deleted['options'])->toHaveCount(12)
        ->and($deleted['options'][0])->toBe(['vote_id' => 1, 'text' => 'أ', 'count' => 4]);
});

it('records del_poll_comment with the deleted ids and the counter change — no comment text', function () {
    $poll = H::poll('Q', [], [], ['comments' => 3]);
    $root = H::comment($poll, 0, 'نص سري طويل');
    $reply = H::comment($poll, $root);

    $this->delete(route('admin.polls.comments.destroy', $root));

    $row = H::auditRows()[0];
    expect([$row->action, $row->entity_type, (int) $row->entity_id])->toBe(['del_poll_comment', 'comment', $root])
        ->and(json_decode($row->metadata, true))->toBe([
            'poll_id' => $poll, 'deleted_tids' => [$root, $reply], 'deleted_count' => 2,
            'counters' => [(string) $poll => ['before' => 3, 'after' => 1, 'removed' => 2]],
        ])
        ->and($row->metadata)->not->toContain('نص سري');
});

it('records markup-looking legacy text as a marker so the mutation is never blocked', function () {
    $poll = H::poll('<b>قديم</b>', ['<i>أ</i>']);

    $this->delete(route('admin.polls.destroy', $poll))->assertSessionHas('success');

    $deleted = json_decode(H::auditRows()[0]->metadata, true)['deleted'];
    expect(H::desc($poll))->toBeNull()
        ->and($deleted['title'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<b>قديم</b>')])
        ->and($deleted['options'][0]['text'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<i>أ</i>')]);
});

it('writes no legacy nuke_islamic_logs row while compatibility is default-OFF', function () {
    expect(config('admin_audit.legacy_compatibility_writes'))->toBeFalse();
    $poll = H::poll('Q', [], [], ['comments' => 1]);
    $tid = H::comment($poll);

    $this->post(route('admin.polls.store'), ['pollTitle' => 'جديد']);
    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'R']);
    $this->delete(route('admin.polls.comments.destroy', $tid));
    $this->delete(route('admin.polls.destroy', $poll));

    expect(H::auditRows())->toHaveCount(4)
        ->and(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

it('rolls the poll back when the audit write fails', function (string $op) {
    $poll = H::poll('Q', ['أ'], [3], ['comments' => 1]);
    $tid = H::comment($poll);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    $call = match ($op) {
        'create' => fn () => $this->post(route('admin.polls.store'), ['pollTitle' => 'جديد', 'options' => [1 => 'أ']]),
        'edit' => fn () => $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'R']),
        'delete' => fn () => $this->delete(route('admin.polls.destroy', $poll)),
        'comment' => fn () => $this->delete(route('admin.polls.comments.destroy', $tid)),
    };
    expect($call)->toThrow(Exception::class, 'audit-down');

    expect(H::snapshot())->toBe($before);
})->with(['create', 'edit', 'delete', 'comment']);

it('rolls back a delete that fails after the choices were removed', function () {
    $poll = H::poll('Q', ['أ', 'ب'], [3, 2]);
    DB::connection('main')->unprepared("CREATE TRIGGER fail_desc BEFORE DELETE ON nuke_poll_desc BEGIN SELECT RAISE(ABORT, 'desc-down'); END");
    $before = H::snapshot();
    $this->withoutExceptionHandling();

    expect(fn () => $this->delete(route('admin.polls.destroy', $poll)))->toThrow(Exception::class, 'desc-down');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});
