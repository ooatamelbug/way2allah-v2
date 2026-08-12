<?php

use App\Domain\Engagement\Http\Controllers\PollController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Engagement Routes (Blueprint v1.0 §7/§10, Roadmap task 3.4)
|--------------------------------------------------------------------------
|
| `surveys/` (PHP-Nuke's native poll system). The clean `/polls` paths
| below were the only routes registered when this module was first built —
| every pretty URL (`survey-{id}.htm`, `surveys.htm`, etc.) targets the
| confirmed-absent `new_modules.php` dispatcher (IF-026's pattern), and at
| the time this comment was first written that was treated as reason
| enough not to register them.
|
| Wave D ("Survey Pretty-URL Aliasing", 2026-08-12): superseded — per this
| project's own governing rule, a missing dispatcher is verification
| evidence only, not exclusion criterion, when the real behavior survives
| elsewhere. It does here: `PollController::index()`/`show()`/`results()`
| already fully implement `surveys/functions.php`'s `pollList()`/
| `pollMain()`/`pollResults()` (the "pattern 1, broken target" precedent
| already used for `fatawa`, `search.htm`, `dumped-lectures`, `wizard.php`).
| The `/polls` paths are left in place, unchanged — both now serve the
| same content.
|
| `survey-comment-*`/`survey-commreply-*`/`survey-showreply-*`/
| `survey-comments.htm` (the poll-comments sub-feature, 6 routes) remain
| deliberately NOT covered — `surveys.md`'s own file list
| (`functions.php`/`item.php`/`polls.php`, all 3 read in full) never found
| a `comments.php` or equivalent; still OPEN / SOURCE UNRECOVERABLE,
| unaffected by this wave.
|
*/

Route::get('/polls', [PollController::class, 'index'])->name('engagement.polls.index');
Route::get('/polls/{poll}', [PollController::class, 'show'])->name('engagement.polls.show');
Route::get('/polls/{poll}/results', [PollController::class, 'results'])->name('engagement.polls.results');
Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->name('engagement.polls.vote');

// Wave D — pretty-URL aliases, `.htaccess:405-407,413`. Reuse the same
// controller actions above directly; no new query logic.
Route::get('/surveys.htm', [PollController::class, 'index'])->name('engagement.surveys.index');
Route::get('/survey-{poll}.htm', [PollController::class, 'show'])
    ->whereNumber('poll')
    ->name('engagement.surveys.show');
Route::get('/survey-results-{poll}.htm', [PollController::class, 'results'])
    ->whereNumber('poll')
    ->name('engagement.surveys.results');

// The sort/threshold variant (`.htaccess:405`) — 4 URL segments against
// results()'s single Poll parameter. Routed through resultsWithVariant()
// (declares all 4 by name) rather than results() directly, to avoid
// Laravel's positional route-parameter binding trap (IF-051) — see that
// method's own docblock.
Route::get('/survey-results-{poll}-{mode}-{order}-{thold}.htm', [PollController::class, 'resultsWithVariant'])
    ->whereNumber('poll')
    ->where('mode', '[a-z]*')
    ->where('order', '[0-9]*')
    ->where('thold', '[0-9\-]*')
    ->name('engagement.surveys.results.variant');
