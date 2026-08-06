<?php

use App\Domain\Engagement\Http\Controllers\PollController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Engagement Routes (Blueprint v1.0 §7/§10, Roadmap task 3.4)
|--------------------------------------------------------------------------
|
| `surveys/` (PHP-Nuke's native poll system) — every one of its intended
| pretty URLs (`survey-{id}.htm`, `surveys.htm`, etc.) routes through
| `.htaccess` to `new_modules.php`, confirmed absent from this codebase
| (IF-026's pattern) — those pretty URLs are NOT reproduced here, since
| doing so would silently imply they currently work when they don't. The
| module is genuinely reachable only via its raw file paths
| (`surveys/polls.php`, `surveys/item.php`), so new clean paths are used
| instead, same as `khotab/dump.php`'s/`pages/social.php`'s own
| raw-path-only precedent.
|
| `survey-comment-*`/`survey-commreply-*`/`survey-showreply-*` (a poll-
| comments sub-feature) are NOT covered — `surveys.md`'s own file list
| (`functions.php`/`item.php`/`polls.php`, all 3 read in full) never found
| a `comments.php` or equivalent; those routes point at the same
| confirmed-absent `new_modules.php` dispatcher with no evidence of a
| reachable implementation anywhere in this codebase.
|
*/

Route::get('/polls', [PollController::class, 'index'])->name('engagement.polls.index');
Route::get('/polls/{poll}', [PollController::class, 'show'])->name('engagement.polls.show');
Route::get('/polls/{poll}/results', [PollController::class, 'results'])->name('engagement.polls.results');
Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->name('engagement.polls.vote');
