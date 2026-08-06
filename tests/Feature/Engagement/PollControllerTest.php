<?php

use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Roadmap task 3.4. Fixes 2 confirmed legacy bugs (`surveys.md` §3/§5/§8,
 * already-known findings, not new): the `cookiedecode()` fatal error for
 * logged-in visitors, and `pollResults()`'s `$poll->holdtitle` reference
 * (results page never showed the poll title).
 */
function useInMemoryMainConnectionForPolls(): void
{
    InMemoryConnection::setup('main', [
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForPolls();
    RateLimiter::clear('poll-vote:*');
});

it('lists standalone polls (artid=0), matching pollList()', function () {
    Poll::create(['pollTitle' => 'Homepage Poll', 'artid' => 0, 'timeStamp' => 100]);
    Poll::create(['pollTitle' => 'Article Poll', 'artid' => 1, 'timeStamp' => 200]);

    $content = $this->get(route('engagement.polls.index'))->assertOk()->getContent();

    expect($content)->toContain('Homepage Poll')->not->toContain('Article Poll');
});

it('shows a poll\'s voting form with its real options', function () {
    $poll = Poll::create(['pollTitle' => 'Best time for lessons?', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'Morning']);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 2, 'optionText' => 'Evening']);

    $this->get(route('engagement.polls.show', $poll))->assertOk()->assertSee('Morning')->assertSee('Evening');
});

it('records a vote and redirects to results', function () {
    $poll = Poll::create(['pollTitle' => 'T', 'artid' => 0, 'voters' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'A', 'optionCount' => 0]);

    $this->post(route('engagement.polls.vote', $poll), ['voteID' => 1])
        ->assertRedirect(route('engagement.polls.results', $poll));

    expect($poll->fresh()->voters)->toBe(1);
    expect(PollOption::where('pollID', $poll->pollID)->where('voteID', 1)->value('optionCount'))->toBe(1);
});

it('rejects a second vote from the same IP within 30 minutes, matching the legacy nuke_poll_check window', function () {
    $poll = Poll::create(['pollTitle' => 'T', 'artid' => 0, 'voters' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'A', 'optionCount' => 0]);

    $this->post(route('engagement.polls.vote', $poll), ['voteID' => 1]);
    $this->post(route('engagement.polls.vote', $poll), ['voteID' => 1]);

    expect($poll->fresh()->voters)->toBe(1);
});

it('surveys.md §5 fix: results page shows the real poll title, not a permanently-empty holdtitle reference', function () {
    $poll = Poll::create(['pollTitle' => 'Which topic interests you most?', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'Fiqh', 'optionCount' => 3]);

    $this->get(route('engagement.polls.results', $poll))->assertOk()->assertSee('Which topic interests you most?');
});

it('results page computes correct percentages across all options', function () {
    $poll = Poll::create(['pollTitle' => 'T', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'A', 'optionCount' => 3]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 2, 'optionText' => 'B', 'optionCount' => 1]);

    $response = $this->get(route('engagement.polls.results', $poll));

    $response->assertOk()->assertSee('75%')->assertSee('25%')->assertSee('مجموع الأصوات: 4');
});

it('redirects the raw legacy surveys/polls.php path to /polls', function () {
    $this->get('/surveys/polls.php')->assertRedirect('/polls');
});
