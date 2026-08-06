<?php

use App\Domain\Admin\Http\Controllers\BroadcastingController;
use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Http\Controllers\ChatRoomAdminController;
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
| Every route requires 'admin.role' (any authenticated admin — a super-admin
| always passes, per AdminGuard/RoleSeeder). Feature-specific actions also
| require 'admin.permission:{module}.{key}', matching the legacy
| $authorization[$module] key from that feature's own menu.php exactly
| (decision-log #9) — a plain admin without that specific permission gets
| a real 403 here. This is stricter than legacy ever was, deliberately:
| sidebar.php's own use of this same data only ever hid the nav link,
| never blocked the page itself (decision-log #10 — a ratified hardening,
| not a port of existing legacy access control).
|
*/

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

    // Roadmap task 5.10 — broadcasting/'s working half.
    Route::middleware(['admin.permission:broadcasting.editstream'])->prefix('broadcasting')->name('broadcasting.')->group(function () {
        Route::get('/{channel}', [BroadcastingController::class, 'edit'])->name('edit');
        Route::put('/{channel}', [BroadcastingController::class, 'update'])->name('update');
    });

    // Roadmap task 5.5 — chat/'s working half. listrooms gates the
    // directory + view; editroom gates the real (rebuilt, IF-034 — this
    // comment previously misnumbered it IF-036) edit capability — matching
    // the legacy key split even though the legacy edit form itself never
    // actually enforced it (no backend existed). View routes accept either
    // permission — an admin holding only editroom still needs to reach the
    // edit form to submit a change (wave-5-verification-review.md Finding
    // 2, decision-log #10).
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::middleware(['admin.permission:chat.listrooms,chat.editroom'])->group(function () {
            Route::get('/', [ChatRoomAdminController::class, 'index'])->name('index');
            Route::get('/{room}', [ChatRoomAdminController::class, 'edit'])->name('edit');
        });
        Route::middleware(['admin.permission:chat.editroom'])->group(function () {
            Route::put('/{room}', [ChatRoomAdminController::class, 'update'])->name('update');
            Route::delete('/{room}/owner/{username}', [ChatRoomAdminController::class, 'removeOwner'])->name('owner.destroy');
            Route::delete('/{room}/speaker/{username}', [ChatRoomAdminController::class, 'removeSpeaker'])->name('speaker.destroy');
        });
    });

    // Roadmap task 5.9 — khotab/uploader(s).php. The add-uploader form is
    // deliberately not routed at all (UploaderController's own docblock).
    Route::middleware(['admin.permission:khotab.uploaders'])->prefix('uploaders')->name('uploaders.')->group(function () {
        Route::get('/', [UploaderController::class, 'index'])->name('index');
        Route::post('/recompute', [UploaderController::class, 'recompute'])->name('recompute');
        Route::post('/vblink', [UploaderController::class, 'backfillVbulletinIdentity'])->name('vblink');
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
