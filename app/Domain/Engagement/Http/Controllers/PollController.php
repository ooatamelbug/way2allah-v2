<?php

namespace App\Domain\Engagement\Http\Controllers;

use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Replaces `surveys/polls.php` + `item.php` (PHP-Nuke's native poll
 * system) — Roadmap task 3.4.
 *
 * Two confirmed legacy bugs fixed, not reproduced (`surveys.md` §3/§5/§8,
 * both already known before this task, not new findings here):
 * - `cookiedecode()` is called but undefined anywhere in the legacy
 *   codebase — a fatal error for any logged-in vBulletin visitor viewing
 *   a voting form (`functions.php:26-28`). That dead `is_user()`/
 *   `cookiedecode()` block served no purpose downstream (`is_user()`'s
 *   own return value was never used for anything) — simply not
 *   reproduced.
 * - `pollResults()`'s `$poll->holdtitle` reference (undefined column,
 *   `functions.php:148`) — the results page shows the real poll title
 *   here (`$poll->pollTitle`, already fetched), not a permanently-empty
 *   bold tag.
 *
 * IP-based, time-windowed vote deduplication uses Laravel's `RateLimiter`
 * (30-minute window, matching the legacy 1800-second window exactly) —
 * `surveys.md`'s own recommendation — replacing the manual
 * `nuke_poll_check` table + inline `DELETE ... WHERE time < ...`
 * housekeeping query that ran on every single vote.
 */
class PollController
{
    public function index(): View
    {
        $polls = Poll::standalone()->orderByDesc('timeStamp')->get();

        return view('engagement.polls.index', compact('polls'));
    }

    /**
     * `surveys/item.php:56-91` — a specific poll's voting form. Every real
     * `.htaccess` route for this page (`survey-{pollID}.htm`) always
     * carries an explicit id (confirmed — none of the 7 confirmed poll
     * routes omit it), so there is no "no id given" case to reproduce
     * here; `index()` is the id-less entry point instead.
     */
    public function show(Poll $poll): View
    {
        return view('engagement.polls.show', [
            'poll' => $poll,
            'options' => $poll->options,
        ]);
    }

    public function results(Poll $poll): View
    {
        $latestFive = Poll::standalone()->where('pollID', '!=', $poll->pollID)->orderByDesc('timeStamp')->limit(5)->get();

        return view('engagement.polls.results', [
            'poll' => $poll,
            'options' => $poll->options,
            'totalVotes' => $poll->totalVotes(),
            'latestFive' => $latestFive,
        ]);
    }

    /**
     * `survey-results-{id}-{mode}-{order}-{thold}.htm` (`.htaccess:405`) —
     * the sort/threshold variant of the results page. `pollResults($pollID)`
     * (`surveys/functions.php:143`) takes only `$pollID` and never reads
     * `$mode`/`$order`/`$thold` anywhere in its body (confirmed by full
     * re-read) — these 3 segments are PHP-Nuke's standard comment-display-
     * preference names (`functions.php:94-96,273-275` build the SAME 3
     * names from `$cookie[4..6]` onto outgoing links to the unbuilt
     * comments sub-feature), carried onto this URL for context but never
     * consumed by the results rendering itself. Same "extra URL segment,
     * functionally ignored" shape as the `location` parameter (IF-047/
     * 048/049), and — for the same reason as that round — declared
     * explicitly by name here rather than pointing a 4-segment route
     * straight at `results(Poll $poll)`: Laravel binds a route's "extra"
     * segments to an under-declared method's parameters positionally, not
     * by name (IF-051). Delegates to the real, unmodified `results()` —
     * no new query logic.
     */
    public function resultsWithVariant(Poll $poll, string $mode, string $order, string $thold): View
    {
        return $this->results($poll);
    }

    /** `functions.php:57-90`'s `pollCollector()`. */
    public function vote(Request $request, Poll $poll): RedirectResponse
    {
        $voteId = (int) $request->input('voteID');
        $key = "poll-vote:{$poll->pollID}:{$request->ip()}";

        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 1800); // 30-minute window, matching the legacy nuke_poll_check dedup exactly.

            PollOption::where('pollID', $poll->pollID)->where('voteID', $voteId)->increment('optionCount');
            $poll->increment('voters');
        }

        return redirect()->route('engagement.polls.results', $poll);
    }
}
