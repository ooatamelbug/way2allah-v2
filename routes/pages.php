<?php

use App\Domain\Pages\Http\Controllers\AboutController;
use App\Domain\Pages\Http\Controllers\MobileAppController;
use App\Domain\Pages\Http\Controllers\PrivacyController;
use App\Domain\Pages\Http\Controllers\QuranMemorizationApplicationController;
use App\Domain\Pages\Http\Controllers\SocialController;
use App\Domain\Pages\Http\Controllers\VisitorFeedbackController;
use App\Domain\Pages\Http\Controllers\VolunteerController;
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
