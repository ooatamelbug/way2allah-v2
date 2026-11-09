<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PollAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 4 — poll comment removal (OLD RemovePollComment
 * → removePollSubComments, `admin/modules/comments.php:76-112`).
 * Scope reproduced: the comment and its whole reply thread. Counter
 * intentionally corrected: minus the rows actually deleted, floored at 0.
 */
beforeEach(function () {
    H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

function removeComment(int $tid)
{
    return test()->delete(route('admin.polls.comments.destroy', $tid));
}

function commentIds(int $poll): array
{
    return DB::connection('main')->table('nuke_pollcomments')->where('pollID', $poll)->orderBy('tid')->pluck('tid')->map(fn ($t) => (int) $t)->all();
}

it('removes a reply-less comment and decrements the counter by one', function () {
    $poll = H::poll('Q', [], [], ['comments' => 3]);
    $a = H::comment($poll);
    $b = H::comment($poll);
    $c = H::comment($poll);

    removeComment($b)->assertRedirect(route('admin.polls.comments', $poll))->assertSessionHas('success');

    expect(commentIds($poll))->toBe([$a, $c])->and((int) H::desc($poll)->comments)->toBe(2);
});

it('removes the whole reply thread at every depth, and nothing outside it', function () {
    $poll = H::poll('Q', [], [], ['comments' => 7]);
    $root = H::comment($poll);
    $r1 = H::comment($poll, $root);
    $r2 = H::comment($poll, $root);
    $r1a = H::comment($poll, $r1);
    $r1a1 = H::comment($poll, $r1a);
    $sibling = H::comment($poll);
    $siblingReply = H::comment($poll, $sibling);

    removeComment($root);

    expect(commentIds($poll))->toBe([$sibling, $siblingReply]);
});

it('decrements by exactly the rows deleted — not OLD over-count', function () {
    // OLD on this thread: -1 (RemovePollComment) -3 (one per descendant)
    // plus a stale-$row decrement per recursion level that found replies,
    // i.e. at least 5 for 4 rows. Correct: 4.
    $poll = H::poll('Q', [], [], ['comments' => 6]);
    $root = H::comment($poll);
    $reply = H::comment($poll, $root);
    H::comment($poll, $reply);
    H::comment($poll, $root);
    H::comment($poll);
    H::comment($poll);

    removeComment($root);

    expect(count(commentIds($poll)))->toBe(2)->and((int) H::desc($poll)->comments)->toBe(2);
});

it('floors the counter at zero when it was already too low', function () {
    $poll = H::poll('Q', [], [], ['comments' => 1]);
    $root = H::comment($poll);
    H::comment($poll, $root);
    H::comment($poll, $root);

    removeComment($root);

    expect(commentIds($poll))->toBe([])->and((int) H::desc($poll)->comments)->toBe(0);
});

it('adjusts only the affected poll counter — no other poll is repaired', function () {
    $poll = H::poll('Q', [], [], ['comments' => 2]);
    $other = H::poll('R', [], [], ['comments' => 99]);   // deliberately wrong; must stay
    $tid = H::comment($poll);
    H::comment($other);

    removeComment($tid);

    expect((int) H::desc($other)->comments)->toBe(99)->and((int) H::desc($poll)->comments)->toBe(1);
});

it('survives a pid cycle without looping', function () {
    $poll = H::poll('Q', [], [], ['comments' => 2]);
    $a = H::comment($poll);
    $b = H::comment($poll, $a);
    DB::connection('main')->table('nuke_pollcomments')->where('tid', $a)->update(['pid' => $b]);

    removeComment($a)->assertSessionHas('success');

    expect(commentIds($poll))->toBe([])->and((int) H::desc($poll)->comments)->toBe(0);
});

it('removes a comment whose poll is gone without touching any counter', function () {
    $poll = H::poll('Q');
    $tid = H::comment($poll);
    DB::connection('main')->table('nuke_poll_desc')->where('pollID', $poll)->delete();

    removeComment($tid)->assertRedirect(route('admin.polls.index'));

    expect(DB::connection('main')->table('nuke_pollcomments')->count())->toBe(0);
});

it('returns 404 for a missing comment and changes nothing (OLD decremented anyway)', function () {
    $poll = H::poll('Q', [], [], ['comments' => 5]);
    $before = H::snapshot();

    removeComment(4040)->assertNotFound();

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('lists a poll comments with thread parents', function () {
    $poll = H::poll('سؤال');
    $root = H::comment($poll, 0, 'تعليق أول');
    H::comment($poll, $root, 'رد على الأول');

    $this->get(route('admin.polls.comments', $poll))->assertOk()
        ->assertSee('تعليق أول')->assertSee('رد على الأول')->assertSee('#'.$root);
});

it('escapes comment markup on the moderation page', function () {
    $poll = H::poll('Q');
    H::comment($poll, 0, '<script>alert(1)</script>');

    $this->get(route('admin.polls.comments', $poll))->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});
