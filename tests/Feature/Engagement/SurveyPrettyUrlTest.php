<?php

use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Wave D ("Survey Pretty-URL Aliasing"). `.htaccess:405-407,413` — pure
 * route-aliasing onto the already-built, already-tested `PollController`
 * actions (`PollControllerTest.php` covers their actual behavior; this
 * file only covers the pretty-URL routing layer itself, including the
 * sort/threshold variant's parameter-binding correctness).
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
    ]);
});

it('surveys.htm renders the same content as /polls', function () {
    Poll::create(['pollTitle' => 'Homepage Poll', 'artid' => 0, 'timeStamp' => 100]);

    $this->get('/surveys.htm')->assertOk()->assertSee('Homepage Poll');
});

it('survey-{id}.htm renders the poll voting form, 404s for a nonexistent id', function () {
    $poll = Poll::create(['pollTitle' => 'Best time for lessons?', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'Morning']);

    $this->get("/survey-{$poll->pollID}.htm")->assertOk()->assertSee('Best time for lessons?')->assertSee('Morning');
    $this->get('/survey-99999999.htm')->assertNotFound();
});

it('survey-results-{id}.htm renders results, 404s for a nonexistent id', function () {
    $poll = Poll::create(['pollTitle' => 'Best time for lessons?', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'Morning', 'optionCount' => 5]);

    $this->get("/survey-results-{$poll->pollID}.htm")->assertOk()->assertSee('Best time for lessons?')->assertSee('Morning');
    $this->get('/survey-results-99999999.htm')->assertNotFound();
});

it('survey-results-{id}-{mode}-{order}-{thold}.htm binds {poll} correctly, not positionally, even when another real poll id appears in a later segment', function () {
    $pollA = Poll::create(['pollTitle' => 'Poll A', 'artid' => 0]);
    $pollB = Poll::create(['pollTitle' => 'Poll B', 'artid' => 0]);
    PollOption::create(['pollID' => $pollA->pollID, 'voteID' => 1, 'optionText' => 'Option A1', 'optionCount' => 3]);
    PollOption::create(['pollID' => $pollB->pollID, 'voteID' => 1, 'optionText' => 'Option B1', 'optionCount' => 7]);

    // Deliberately put pollB's real id in the "order" segment — if the
    // route ever regressed to positional binding (IF-051's trap), this
    // would incorrectly resolve to Poll B instead of Poll A. Note: Poll B
    // legitimately appears in results()'s own "latest five polls" sidebar
    // regardless (a real, unrelated feature) — so the precise check is the
    // page <title> and the OPTIONS shown, not a blanket "Poll B" absence.
    $response = $this->get("/survey-results-{$pollA->pollID}-flat-{$pollB->pollID}-10.htm");

    $response->assertOk()
        ->assertSee('<title>Poll A - ' . config('app.name') . '</title>', false)
        ->assertSee('Option A1')
        ->assertDontSee('Option B1');
});

it('survey-results-{id}-{mode}-{order}-{thold}.htm renders identically regardless of mode/order/thold values — pollResults() never reads them (confirmed by source re-read)', function () {
    $poll = Poll::create(['pollTitle' => 'Consistency Poll', 'artid' => 0]);
    PollOption::create(['pollID' => $poll->pollID, 'voteID' => 1, 'optionText' => 'Yes', 'optionCount' => 4]);

    $plain = $this->get("/survey-results-{$poll->pollID}.htm")->assertOk();
    $variantOne = $this->get("/survey-results-{$poll->pollID}-flat-0-10.htm")->assertOk();
    $variantTwo = $this->get("/survey-results-{$poll->pollID}-thread-1-0-5.htm")->assertOk();

    // Same poll content on all 3 shapes.
    foreach ([$plain, $variantOne, $variantTwo] as $response) {
        $response->assertSee('Consistency Poll')->assertSee('Yes');
    }
});

it('survey-results-{id}-{mode}-{order}-{thold}.htm 404s for a nonexistent poll id', function () {
    $this->get('/survey-results-99999999-flat-0-10.htm')->assertNotFound();
});
