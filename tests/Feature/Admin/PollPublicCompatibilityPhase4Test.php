<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\PollAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 4 — a poll created and edited in the AdminCP
 * works on the existing, unchanged public poll pages: listing, detail,
 * voting and results.
 */
beforeEach(function () {
    H::setUp();
    RateLimiter::clear('poll-vote:*');
    $this->actingAs(H::superAdmin(), 'admin');
});

it('shows an admin-created poll on the public list, detail, vote and results pages', function () {
    $this->post(route('admin.polls.store'), ['pollTitle' => 'استفتاء جديد', 'options' => [1 => 'نعم', 2 => 'لا']]);
    $poll = (int) DB::connection('main')->table('nuke_poll_desc')->value('pollID');

    $this->get(route('engagement.polls.index'))->assertOk()->assertSee('استفتاء جديد');
    $this->get(route('engagement.polls.show', $poll))->assertOk()->assertSee('نعم')->assertSee('لا');

    $this->post(route('engagement.polls.vote', $poll), ['voteID' => 2])->assertRedirect(route('engagement.polls.results', $poll));

    expect((int) DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->where('voteID', 2)->value('optionCount'))->toBe(1)
        ->and((int) H::desc($poll)->voters)->toBe(1);
    $this->get(route('engagement.polls.results', $poll))->assertOk()->assertSee('استفتاء جديد')->assertSee('لا');
});

it('keeps votes and results through an edit, and shows the new texts publicly', function () {
    $poll = H::poll('قبل', ['أ', 'ب'], [6, 2]);

    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'بعد', 'options' => [1 => 'أ معدل', 2 => 'ب']]);

    $this->get(route('engagement.polls.results', $poll))->assertOk()->assertSee('بعد')->assertSee('أ معدل');
    expect((int) DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->sum('optionCount'))->toBe(8);
});

it('removes a deleted poll from the public pages', function () {
    $poll = H::poll('سيحذف');

    $this->delete(route('admin.polls.destroy', $poll));

    $this->get(route('engagement.polls.index'))->assertOk()->assertDontSee('سيحذف');
    $this->get(route('engagement.polls.show', $poll))->assertNotFound();
});
