<?php

use App\Domain\Pages\Http\Controllers\AboutController;
use App\Domain\Pages\Http\Controllers\MobileAppController;
use App\Domain\Pages\Http\Controllers\PrivacyController;
use App\Domain\Pages\Http\Controllers\QuranMemorizationApplicationController;
use App\Domain\Pages\Http\Controllers\RamadanController;
use App\Domain\Pages\Http\Controllers\ShareController;
use App\Domain\Pages\Http\Controllers\SocialController;
use App\Domain\Pages\Http\Controllers\VisitorFeedbackController;
use App\Domain\Pages\Http\Controllers\VolunteerController;
use App\Domain\Pages\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pages Routes (Blueprint v1.0 §7/§18, Roadmap tasks 2.1 + 2.4 + 2.5)
|--------------------------------------------------------------------------
|
| Wave 2's zero-risk static wins. None of these legacy files had a matching
| .htaccess rule (pages.md §2) — every one was reachable only at its raw
| legacy path. Those raw paths are registered via LegacyUrlCompatibility
| (config/legacy-url-map.php), not hardcoded into this file.
|
*/

Route::get('/privacy', PrivacyController::class)->name('pages.privacy');
Route::get('/about', AboutController::class)->name('pages.about');

// Four-Route Migration Gap Audit — `landing_page.htm` (ROUTE_MAPPING_GAP,
// not a new page). `.htaccess:417` — `landing_page.htm` → `new_modules.php
// ?name=help&op=about_page` — targets the same confirmed-missing
// `new_modules.php` dispatcher already found for `share.htm`/`ramadan.htm`
// (decision-log #8/#18/#19), but `header.php:346`'s real, standing nav
// link (`<a href="landing_page.htm">من نحن</a>` — the exact label `/about`
// itself renders) and `help/`'s directory contents (only `about.php` and
// `share.php` exist — `op=about_page` can only mean `help/about.php`)
// together confirm this is the SAME content already live at `/about`,
// under a second legacy pretty-URL. Registered directly at its exact
// legacy path to the same `AboutController` — no redirect, same pattern
// already established for `social.htm` above (a real nav link with a
// missing dispatcher rule) and for `/khotab/search` +
// `/video-advanced-search.htm` both reaching `KhotabSearchController`
// (routes/content.php) — not a new controller, not a duplicated view.
Route::get('/landing_page.htm', AboutController::class)->name('pages.landing-page');

Route::get('/mobile-app', MobileAppController::class)->name('pages.mobile-app');
Route::get('/visitor-feedback', VisitorFeedbackController::class)->name('pages.visitor-feedback');
Route::get('/quran-memorization-application', QuranMemorizationApplicationController::class)->name('pages.quran-memorization-application');
Route::get('/volunteer', VolunteerController::class)->name('pages.volunteer');

// Roadmap task 2.5. Unlike every route above, `social.htm` is kept at its
// EXACT legacy path rather than getting a new clean path + redirect —
// header.php's account-dropdown menu links to this exact pretty path
// twice, in permanent site nav, even though no .htaccess rule ever backed
// it (confirmed: exhaustive grep, zero matches — that standing link 404s
// in production today). Same rationale as routes/content.php's "kept at
// exact legacy path" routes, just arriving at it from the opposite
// direction (a real link with a missing rule, not a real rule already
// live).
Route::get('/social.htm', SocialController::class)->name('pages.social');

// Roadmap task 6.3. Unlike Wave 2's pages above, `ramadan.htm`/`share.htm`
// ARE real, live .htaccess rules (.htaccess:114,373) — both target the
// same missing `new_modules.php` dispatcher already confirmed absent for
// `fatawa`/`advanced-search` ("pattern 1, broken target", decision-log
// #8/#18/#19). Kept at their exact legacy path here, same as
// routes/content.php's fatawa routes, rather than routed through
// LegacyUrlCompatibility. `ramadan.htm` replaces all 3 duplicated
// ramadan*.php files with one consolidated, parameterized view
// (pages.md §9's Migration Decision Classification) — see
// RamadanController's docblock for exactly which legacy file's boundaries
// and counter behavior were kept authoritative.
Route::get('/ramadan.htm', RamadanController::class)->name('pages.ramadan');
Route::get('/share.htm', ShareController::class)->name('pages.share');

// wizard.php — Wave C ("Public Locations & Da'wah Registration Surfaces").
// No .htaccess rule of any kind (confirmed) — same raw-path-only profile
// as khotab/dump.php. Kept at its exact legacy raw path (`/wizard.php`),
// not given a new clean path, since nothing else in the codebase links to
// it under a different name. See WizardController's own docblock for the
// full record of preserved quirks (the `password`-named phone field, the
// discarded `rpassword`, the no-redirect-after-insert behavior). IF-050.
Route::get('/wizard.php', [WizardController::class, 'show'])->name('pages.wizard.show');
Route::post('/wizard.php', [WizardController::class, 'store'])->name('pages.wizard.store');
