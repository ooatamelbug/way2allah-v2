<?php

use App\Domain\Admin\Http\Controllers\AdminAuthController;
use App\Domain\Admin\Http\Controllers\BroadcastingController;
use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Http\Controllers\LinkQualityStatsController;
use App\Domain\Admin\Http\Controllers\UploaderController;
use App\Domain\Admin\Http\Controllers\LocationsController;
use App\Domain\Admin\Http\Controllers\PermissionController;
use App\Domain\Admin\Http\Controllers\QuestionnaireController;
use App\Domain\Admin\Http\Controllers\SoundcloudController;
use App\Domain\Admin\Http\Controllers\SurveyController;
use App\Domain\Admin\Http\Controllers\YoutubeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes (Blueprint v1.0 §7, Roadmap task 1.6, Wave 5)
|--------------------------------------------------------------------------
|
| Every FEATURE route below requires 'admin.role' (any authenticated admin
| — a super-admin always passes, per AdminGuard/RoleSeeder). Feature-specific
| actions also require 'admin.permission:{module}.{key}', matching the
| legacy $authorization[$module] key from that feature's own menu.php
| exactly (decision-log #9) — a plain admin without that specific
| permission gets a real 403 here. This is stricter than legacy ever was,
| deliberately: sidebar.php's own use of this same data only ever hid the
| nav link, never blocked the page itself (decision-log #10 — a ratified
| hardening, not a port of existing legacy access control).
|
| The 3 entry-point routes just below (`/admincp/` GET/login POST/logout
| POST) are deliberately OUTSIDE the 'admin.role' group — they ARE the
| unauthenticated surface, per the owner decision superseding Wave 5's
| "no login/dashboard UI" exclusion (decision-log entry, `/admincp/`
| Login + Dashboard Completion). `AdminAuthController::entry()` still
| internally requires a real AdminGuard session to render the dashboard
| half of its own single-route branch — this is not a weakening of the
| feature routes' own authorization below, which is unchanged.
|
*/

Route::prefix('admincp')->name('admin.')->group(function () {
    Route::get('/', [AdminAuthController::class, 'entry'])->name('entry');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::middleware(['admin.role'])->prefix('admincp')->name('admin.')->group(function () {

    // Roadmap task 5.2 — admincp/survey/. modsurvey = admincp/survey/menu.php's
    // own top-level authorization key (create/moderate); modquestion/modanalysis
    // exist in the legacy key set but have no distinct code path in the
    // audited source (single `menu.php`-level gate) — reproduced as one gate,
    // not invented sub-gates the legacy code itself never implemented.
    Route::middleware(['admin.permission:survey.modsurvey'])->prefix('survey')->name('survey.')->group(function () {
        Route::get('/', [SurveyController::class, 'index'])->name('index');
        Route::delete('/{survey}', [SurveyController::class, 'destroy'])->name('destroy');
        Route::get('/create', [SurveyController::class, 'create'])->name('create');
        Route::post('/', [SurveyController::class, 'store'])->name('store');
        Route::get('/{survey}/questions', [SurveyController::class, 'questionsIndex'])->name('questions.index');
        Route::post('/{survey}/questions', [SurveyController::class, 'storeQuestion'])->name('questions.store');
        Route::post('/{survey}/questions/reorder', [SurveyController::class, 'reorderQuestions'])->name('questions.reorder');
        Route::delete('/{survey}/questions/{question}', [SurveyController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::get('/{survey}/stats', [SurveyController::class, 'stats'])->name('stats');
        Route::get('/{survey}/answers/{answer}', [SurveyController::class, 'showAnswer'])->name('answer.show');
        Route::get('/{survey}/all-stats', [SurveyController::class, 'allStats'])->name('all-stats');
    });

    // Roadmap task 5.3 — one real permission-editor implementation,
    // replacing all 5 legacy edit_author.php copies. Editing another
    // admin's permissions/password is super-admin-only — the legacy
    // copies gate this no more finely than "any logged-in admin" (a real,
    // pre-existing over-permissive gap, not reproduced). Ratified under
    // decision-log #10, not just this comment.
    Route::middleware(['admin.role:super-admin'])->prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/{admin}', [PermissionController::class, 'edit'])->name('edit');
        Route::put('/{admin}', [PermissionController::class, 'update'])->name('update');
        Route::put('/{admin}/password', [PermissionController::class, 'updatePassword'])->name('password');
    });

    // Roadmap task 5.4 — soundcloud/youtube/locations.
    Route::middleware(['admin.permission:soundcloud.update_soundcloud'])->prefix('soundcloud')->name('soundcloud.')->group(function () {
        Route::get('/', [SoundcloudController::class, 'edit'])->name('edit');
        Route::post('/', [SoundcloudController::class, 'update'])->name('update');
    });

    Route::middleware(['admin.permission:youtube.update_youtube'])->prefix('youtube')->name('youtube.')->group(function () {
        Route::get('/', [YoutubeController::class, 'edit'])->name('edit');
        Route::post('/', [YoutubeController::class, 'store'])->name('store');
        Route::delete('/{index}', [YoutubeController::class, 'destroy'])->name('destroy');
    });

    // View routes accept either permission — an admin holding only
    // del_location still needs to reach the list to find something to
    // delete (wave-5-verification-review.md Finding 2, decision-log #10).
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::middleware(['admin.permission:locations.add_location,locations.del_location'])->group(function () {
            Route::get('/', [LocationsController::class, 'index'])->name('index');
            Route::get('/{location}/edit', [LocationsController::class, 'edit'])->name('edit');
        });
        Route::middleware(['admin.permission:locations.add_location'])->group(function () {
            Route::get('/create', [LocationsController::class, 'create'])->name('create');
            Route::post('/', [LocationsController::class, 'store'])->name('store');
            Route::put('/{location}', [LocationsController::class, 'update'])->name('update');
        });
        Route::middleware(['admin.permission:locations.del_location'])->group(function () {
            Route::delete('/{location}', [LocationsController::class, 'destroy'])->name('destroy');
        });
    });

    // Roadmap task 5.8 — questionnaire/. listallquest/listquest gate both
    // routes (the legacy source has no distinct code path per key, same
    // single-menu.php-level gate treatment as survey.modsurvey above).
    Route::middleware(['admin.permission:questionnaire.listallquest,questionnaire.listquest'])->prefix('questionnaire')->name('questionnaire.')->group(function () {
        Route::get('/', [QuestionnaireController::class, 'index'])->name('index');
        Route::get('/{response}', [QuestionnaireController::class, 'show'])->name('show');
    });

    // Roadmap task 5.10 — broadcasting/'s working half. `index` added in the
    // Admin Broadcasting Final Closure task (2026-08-22) — reconstructs
    // legacy `index.php?op=editstream`'s real channel-list branch, source-
    // confirmed functional though never linked from legacy's own sidebar
    // (menu.php hardcoded a direct link to a single channel, id=51,
    // bypassing the list entirely). Same permission key as `edit`/`update`
    // — legacy's own `$authorization['broadcasting']['editstream']` gated
    // this exact operation, no new key introduced.
    Route::middleware(['admin.permission:broadcasting.editstream'])->prefix('broadcasting')->name('broadcasting.')->group(function () {
        Route::get('/', [BroadcastingController::class, 'index'])->name('index');
        Route::get('/{channel}', [BroadcastingController::class, 'edit'])->name('edit');
        Route::put('/{channel}', [BroadcastingController::class, 'update'])->name('update');
    });

    // Roadmap task 5.5's `admin.chat.*` routes (FlashChat live voice-room
    // administration) were removed here — Final Migration Owner-Decision
    // Closure (2026-08-23, decision-log): the live-room feature itself is
    // retired with no replacement (Business Confirmation #4), and the
    // owner decided `CHAT_ROOM_ADMIN = REMOVE` rather than keep admin
    // tooling for a feature that no longer exists. The `chat.listrooms`/
    // `chat.editroom` Spatie permission definitions are left seeded
    // (harmless, real legacy key names, matching the already-established
    // `EXPECTED_LEGACY_PERMISSION_METADATA` precedent) — not removed here.
    // `App\Domain\Content\Http\Controllers\ChatRoomLessonController`'s
    // recorded-lesson-browsing routes (chat_room.htm, chat_author_{id}.htm,
    // chat_lesson_{id}.htm, lesson-download-{id}.htm, in routes/content.php)
    // are a completely separate, unrelated, active capability — untouched.

    // Roadmap task 5.9 — khotab/uploader(s).php. The add-uploader form is
    // deliberately not routed at all (UploaderController's own docblock).
    Route::middleware(['admin.permission:khotab.uploaders'])->prefix('uploaders')->name('uploaders.')->group(function () {
        Route::get('/', [UploaderController::class, 'index'])->name('index');
        Route::post('/recompute', [UploaderController::class, 'recompute'])->name('recompute');
        Route::post('/vblink', [UploaderController::class, 'backfillVbulletinIdentity'])->name('vblink');
    });

    // Roadmap task 6.7 — khotab/telawah/mirror link-quality stats & repair
    // (not confirmation-gated, distinct from task 6.4's still-gated CRUD).
    Route::prefix('link-quality')->name('link-quality.')->group(function () {
        Route::middleware(['admin.permission:khotab.repair'])->prefix('mirror')->name('mirror.')->group(function () {
            Route::get('/', [LinkQualityStatsController::class, 'mirror'])->name('index');
            Route::post('/recompute', [LinkQualityStatsController::class, 'recomputeMirror'])->name('recompute');
            Route::post('/{mirror}/recheck', [LinkQualityStatsController::class, 'recheckMirror'])->name('recheck');
            Route::post('/{mirror}/fix-size', [LinkQualityStatsController::class, 'fixSizeMirror'])->name('fix-size');
        });
        Route::middleware(['admin.permission:khotab.repair'])->prefix('khotab')->name('khotab.')->group(function () {
            Route::get('/', [LinkQualityStatsController::class, 'khotab'])->name('index');
            Route::post('/recompute', [LinkQualityStatsController::class, 'recomputeKhotab'])->name('recompute');
            Route::post('/{khotabItem}/recheck', [LinkQualityStatsController::class, 'recheckKhotab'])->name('recheck');
            Route::post('/{khotabItem}/fix-size', [LinkQualityStatsController::class, 'fixSizeKhotab'])->name('fix-size');
            Route::get('/large-files', [LinkQualityStatsController::class, 'khotabLargeFiles'])->name('large-files');
        });
        Route::middleware(['admin.permission:telawah.repair'])->prefix('telawah')->name('telawah.')->group(function () {
            Route::get('/', [LinkQualityStatsController::class, 'telawah'])->name('index');
            Route::post('/recompute', [LinkQualityStatsController::class, 'recomputeTelawah'])->name('recompute');
            Route::post('/{telawahItem}/recheck', [LinkQualityStatsController::class, 'recheckTelawah'])->name('recheck');
            Route::post('/{telawahItem}/fix-size', [LinkQualityStatsController::class, 'fixSizeTelawah'])->name('fix-size');
        });
    });

    // Roadmap tasks 5.6/5.7 — rebuilt authors/backup, no fixed default password.
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::middleware(['admin.permission:authors.liststuff'])->group(function () {
            Route::get('/', [AdminStaffController::class, 'index'])->name('index');
        });
        Route::middleware(['admin.permission:authors.addstuff'])->group(function () {
            Route::get('/create', [AdminStaffController::class, 'create'])->name('create');
            Route::post('/', [AdminStaffController::class, 'store'])->name('store');
        });
    });
});
