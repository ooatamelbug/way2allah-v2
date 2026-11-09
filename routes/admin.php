<?php

use App\Domain\Admin\Http\Controllers\AdminAuditLogController;
use App\Domain\Admin\Http\Controllers\AdminAuthController;
use App\Domain\Admin\Http\Controllers\AdminStaffController;
use App\Domain\Admin\Http\Controllers\AdvfileController;
use App\Domain\Admin\Http\Controllers\AnasheedGroupAdminController;
use App\Domain\Admin\Http\Controllers\AnasheedItemAdminController;
use App\Domain\Admin\Http\Controllers\AnasheedMirrorController;
use App\Domain\Admin\Http\Controllers\AnasheedWithoutAuthorsController;
use App\Domain\Admin\Http\Controllers\AnasheedWithoutCatsController;
use App\Domain\Admin\Http\Controllers\AnasheedWithoutChannelsController;
use App\Domain\Admin\Http\Controllers\BroadcastingController;
use App\Domain\Admin\Http\Controllers\CategoryAssignmentController;
use App\Domain\Admin\Http\Controllers\CdBannerController;
use App\Domain\Admin\Http\Controllers\CdGroupAdminController;
use App\Domain\Admin\Http\Controllers\CdReleaseController;
use App\Domain\Admin\Http\Controllers\FatwaAdminController;
use App\Domain\Admin\Http\Controllers\FatwaGeneralQuestionAdminController;
use App\Domain\Admin\Http\Controllers\FatwaTopicAdminController;
use App\Domain\Admin\Http\Controllers\KhotabAuthorAdminController;
use App\Domain\Admin\Http\Controllers\KhotabGroupAdminController;
use App\Domain\Admin\Http\Controllers\KhotabItemAdminController;
use App\Domain\Admin\Http\Controllers\KhotabLinkReportController;
use App\Domain\Admin\Http\Controllers\KhotabMainAdminController;
use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use App\Domain\Admin\Http\Controllers\KhotabSeriesAdminController;
use App\Domain\Admin\Http\Controllers\LinkQualityStatsController;
use App\Domain\Admin\Http\Controllers\LocationsController;
use App\Domain\Admin\Http\Controllers\MobileAdminController;
use App\Domain\Admin\Http\Controllers\PermissionController;
use App\Domain\Admin\Http\Controllers\PollAdminController;
use App\Domain\Admin\Http\Controllers\QuestionnaireController;
use App\Domain\Admin\Http\Controllers\SoundcloudController;
use App\Domain\Admin\Http\Controllers\SurveyController;
use App\Domain\Admin\Http\Controllers\TelawahGroupAdminController;
use App\Domain\Admin\Http\Controllers\TelawahItemAdminController;
use App\Domain\Admin\Http\Controllers\TelawahLinkReportController;
use App\Domain\Admin\Http\Controllers\TelawahMirrorController;
use App\Domain\Admin\Http\Controllers\UploaderController;
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

    // OLD Admin Migration Phase 1 — Mirror Management (Khotab + Anasheed).
    // Each mutation route carries the exact flat permission for its
    // operation; the action behind it re-checks through
    // AdminRecordAuthorizer at the mutation boundary. Viewing an item's
    // mirrors accepts any of that module's three mirror permissions, so an
    // admin holding only delmirror can still reach something to delete
    // (same reasoning as the locations group above). Khotab re-parenting is
    // gated by khotab.editmirror — the token OLD's MoveMirror checked; no
    // movemirror permission exists.
    Route::prefix('mirrors')->name('mirrors.')->group(function () {
        Route::prefix('khotab')->name('khotab.')->group(function () {
            Route::middleware(['admin.permission:khotab.addmirror,khotab.editmirror,khotab.delmirror'])->group(function () {
                Route::get('/', [KhotabMirrorController::class, 'index'])->name('index');
                Route::get('/items/{khotabItem}', [KhotabMirrorController::class, 'show'])->whereNumber('khotabItem')->name('show');
            });
            Route::middleware(['admin.permission:khotab.addmirror'])->group(function () {
                Route::post('/items/{khotabItem}', [KhotabMirrorController::class, 'store'])->whereNumber('khotabItem')->name('store');
            });
            Route::middleware(['admin.permission:khotab.editmirror'])->group(function () {
                Route::post('/clipboard/clear', [KhotabMirrorController::class, 'clearPick'])->name('clipboard.clear');
                Route::get('/{mirror}/edit', [KhotabMirrorController::class, 'edit'])->whereNumber('mirror')->name('edit');
                Route::put('/{mirror}', [KhotabMirrorController::class, 'update'])->whereNumber('mirror')->name('update');
                Route::post('/{mirror}/pick', [KhotabMirrorController::class, 'pick'])->whereNumber('mirror')->name('pick');
                Route::post('/{mirror}/move', [KhotabMirrorController::class, 'move'])->whereNumber('mirror')->name('move');
            });
            Route::middleware(['admin.permission:khotab.delmirror'])->group(function () {
                Route::delete('/{mirror}', [KhotabMirrorController::class, 'destroy'])->whereNumber('mirror')->name('destroy');
            });
        });

        Route::prefix('anasheed')->name('anasheed.')->group(function () {
            Route::middleware(['admin.permission:anasheed.addmirror,anasheed.editmirror,anasheed.delmirror'])->group(function () {
                Route::get('/', [AnasheedMirrorController::class, 'index'])->name('index');
                Route::get('/items/{anasheedItem}', [AnasheedMirrorController::class, 'show'])->whereNumber('anasheedItem')->name('show');
            });
            Route::middleware(['admin.permission:anasheed.addmirror'])->group(function () {
                Route::post('/items/{anasheedItem}', [AnasheedMirrorController::class, 'store'])->whereNumber('anasheedItem')->name('store');
            });
            Route::middleware(['admin.permission:anasheed.editmirror'])->group(function () {
                Route::get('/{anasheedMirror}/edit', [AnasheedMirrorController::class, 'edit'])->whereNumber('anasheedMirror')->name('edit');
                Route::put('/{anasheedMirror}', [AnasheedMirrorController::class, 'update'])->whereNumber('anasheedMirror')->name('update');
            });
            Route::middleware(['admin.permission:anasheed.delmirror'])->group(function () {
                Route::delete('/{anasheedMirror}', [AnasheedMirrorController::class, 'destroy'])->whereNumber('anasheedMirror')->name('destroy');
            });
        });

        // Closure correction (2026-10-15) — Telawah: edit and delete only, the
        // half of OLD's Telawah mirror CRUD that worked (OLD disabled insert).
        Route::prefix('telawah')->name('telawah.')->group(function () {
            Route::middleware(['admin.permission:telawah.editmirror,telawah.delmirror'])->group(function () {
                Route::get('/', [TelawahMirrorController::class, 'index'])->name('index');
                Route::get('/items/{telawahItem}', [TelawahMirrorController::class, 'show'])->whereNumber('telawahItem')->name('show');
            });
            Route::middleware(['admin.permission:telawah.editmirror'])->group(function () {
                Route::get('/{telawahMirror}/edit', [TelawahMirrorController::class, 'edit'])->whereNumber('telawahMirror')->name('edit');
                Route::put('/{telawahMirror}', [TelawahMirrorController::class, 'update'])->whereNumber('telawahMirror')->name('update');
            });
            Route::middleware(['admin.permission:telawah.delmirror'])->group(function () {
                Route::delete('/{telawahMirror}', [TelawahMirrorController::class, 'destroy'])->whereNumber('telawahMirror')->name('destroy');
            });
        });
    });

    // OLD Admin Migration Phase 2 — Administration Reporting & Observability.
    // Every route here is a read-only GET. The recheck / fix-size actions the
    // item link-health page offers are the EXISTING link-quality routes above,
    // reused unchanged.
    Route::middleware(['admin.permission:khotab.repair'])->prefix('link-quality/khotab')->name('link-quality.khotab.')->group(function () {
        Route::get('/lost', [KhotabLinkReportController::class, 'lostLinks'])->name('lost');
        Route::get('/items/{khotabItem}', [KhotabLinkReportController::class, 'item'])->whereNumber('khotabItem')->name('item');
    });

    // OLD Admin Migration, contextual surface C5 — the Telawah item link-health
    // page (read-only), the counterpart of the Khotab page above. Same gate as
    // the existing Telawah recheck / fix-size routes it posts to.
    Route::middleware(['admin.permission:telawah.repair'])->prefix('link-quality/telawah')->name('link-quality.telawah.')->group(function () {
        Route::get('/items/{telawahItem}', [TelawahLinkReportController::class, 'item'])->whereNumber('telawahItem')->name('item');
    });

    // OLD Admin Migration Phase 3 — Category Assignment (OA-3). One flat
    // permission per domain gates both add and remove (OLD had no delcat
    // key); each action re-checks it through AdminRecordAuthorizer.
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::middleware(['admin.permission:khotab.addcat'])->prefix('khotab')->name('khotab.')->group(function () {
            Route::get('/', [CategoryAssignmentController::class, 'khotabIndex'])->name('index');
            Route::get('/items/{khotabItem}', [CategoryAssignmentController::class, 'khotabItem'])->whereNumber('khotabItem')->name('item');
            Route::post('/items/{khotabItem}/add', [CategoryAssignmentController::class, 'addToKhotabItem'])->whereNumber('khotabItem')->name('item.add');
            Route::post('/items/{khotabItem}/remove', [CategoryAssignmentController::class, 'removeFromKhotabItem'])->whereNumber('khotabItem')->name('item.remove');
            Route::get('/series/{series}', [CategoryAssignmentController::class, 'series'])->whereNumber('series')->name('series');
            Route::post('/series/{series}/add-all', [CategoryAssignmentController::class, 'addToAllInSeries'])->whereNumber('series')->name('series.add-all');
            Route::post('/series/{series}/remove-all', [CategoryAssignmentController::class, 'removeFromAllInSeries'])->whereNumber('series')->name('series.remove-all');
            Route::get('/groups/{group}', [CategoryAssignmentController::class, 'group'])->whereNumber('group')->name('group');
            Route::post('/groups/{group}/add-all', [CategoryAssignmentController::class, 'addToAllInGroup'])->whereNumber('group')->name('group.add-all');
            Route::post('/groups/{group}/remove-all', [CategoryAssignmentController::class, 'removeFromAllInGroup'])->whereNumber('group')->name('group.remove-all');
        });

        Route::middleware(['admin.permission:anasheed.addcat'])->prefix('anasheed')->name('anasheed.')->group(function () {
            Route::get('/', [CategoryAssignmentController::class, 'anasheedIndex'])->name('index');
            Route::get('/items/{anasheedItem}', [CategoryAssignmentController::class, 'anasheedItem'])->whereNumber('anasheedItem')->name('item');
            Route::post('/items/{anasheedItem}/add', [CategoryAssignmentController::class, 'addToAnasheedItem'])->whereNumber('anasheedItem')->name('item.add');
            Route::post('/items/{anasheedItem}/remove', [CategoryAssignmentController::class, 'removeFromAnasheedItem'])->whereNumber('anasheedItem')->name('item.remove');
        });
    });

    // OLD public admin parity — the Anasheed group page's «لوحة تحكم قسم
    // المنوعات» (owner, 2026-10-23): five of its six controls, each gated by
    // OLD's own `nuke_anasheed_mod` token. «ترتيب القسم» is dead in
    // production and has no route. Only a real group binds: OLD Main's
    // Group(0) panel is out of scope. Each mutation is a non-GET.
    Route::prefix('anasheed/groups/{anasheedGroup}')->whereNumber('anasheedGroup')->name('anasheed.groups.')->group(function () {
        Route::middleware(['admin.permission:anasheed.addicon'])->group(function () {
            Route::get('/icon', [AnasheedGroupAdminController::class, 'icon'])->name('icon');
            Route::post('/icon', [AnasheedGroupAdminController::class, 'uploadIcon'])->name('icon.store');
        });
        Route::middleware(['admin.permission:anasheed.editgroup'])->group(function () {
            Route::get('/edit', [AnasheedGroupAdminController::class, 'edit'])->name('edit');
            Route::put('/', [AnasheedGroupAdminController::class, 'update'])->name('update');
        });
        Route::middleware(['admin.permission:anasheed.addanasheed'])->group(function () {
            Route::get('/items/create', [AnasheedGroupAdminController::class, 'createItem'])->name('items.create');
            Route::post('/items', [AnasheedGroupAdminController::class, 'storeItem'])->name('items.store');
        });
        Route::middleware(['admin.permission:anasheed.addgroup'])->group(function () {
            Route::get('/subgroups/create', [AnasheedGroupAdminController::class, 'createSubGroup'])->name('subgroups.create');
            Route::post('/subgroups', [AnasheedGroupAdminController::class, 'storeSubGroup'])->name('subgroups.store');
        });
        Route::middleware(['admin.permission:anasheed.delgroup'])->group(function () {
            Route::get('/delete', [AnasheedGroupAdminController::class, 'confirmDestroy'])->name('delete');
            Route::delete('/', [AnasheedGroupAdminController::class, 'destroy'])->name('destroy');
        });
    });

    // OLD public admin parity — the Anasheed item page's «لوحة تحكم المادة» and
    // its comment controls (owner, 2026-10-23): the five working OLD controls
    // the Phase 1 / 3 mirror and category routes did not already cover, each
    // gated by its owner-mapped `nuke_anasheed_mod` token. Each mutation is a
    // non-GET.
    Route::prefix('anasheed/items/{anasheedItem}')->whereNumber('anasheedItem')->name('anasheed.items.')->group(function () {
        Route::middleware(['admin.permission:anasheed.addbanner'])->group(function () {
            Route::get('/frame', [AnasheedItemAdminController::class, 'frame'])->name('frame');
            Route::post('/frame', [AnasheedItemAdminController::class, 'uploadFrame'])->name('frame.store');
        });
        Route::middleware(['admin.permission:anasheed.editanasheed'])->group(function () {
            Route::get('/edit', [AnasheedItemAdminController::class, 'edit'])->name('edit');
            Route::put('/', [AnasheedItemAdminController::class, 'update'])->name('update');
        });
        Route::middleware(['admin.permission:anasheed.delanasheed'])->group(function () {
            Route::get('/delete', [AnasheedItemAdminController::class, 'confirmDestroy'])->name('delete');
            Route::delete('/', [AnasheedItemAdminController::class, 'destroy'])->name('destroy');
        });
        Route::post('/comments', [AnasheedItemAdminController::class, 'storeComment'])
            ->middleware(['admin.permission:anasheed.addcomment'])->name('comments.store');
    });
    // OLD gates WaitingList(id) and AddComment with `addcomment`, DelComment with `delcomment`.
    Route::prefix('anasheed/comments/{anasheedComment}')->whereNumber('anasheedComment')->name('anasheed.comments.')->group(function () {
        Route::get('/edit', [AnasheedItemAdminController::class, 'editComment'])
            ->middleware(['admin.permission:anasheed.addcomment'])->name('edit');
        Route::put('/publish', [AnasheedItemAdminController::class, 'publishComment'])
            ->middleware(['admin.permission:anasheed.addcomment'])->name('publish');
        Route::post('/hide', [AnasheedItemAdminController::class, 'hideComment'])
            ->middleware(['admin.permission:anasheed.delcomment'])->name('hide');
    });

    // OLD public admin parity — Telawah (owner, 2026-10-29): Main's «إضافة
    // قارىء», the group page's four working controls and the item page's
    // edit / delete / move, each gated by OLD's own `nuke_telawah_mod` token
    // (move: `edittelawah`, OLD had none). Each mutation is a non-GET.
    Route::prefix('telawah')->name('telawah.')->group(function () {
        Route::middleware(['admin.permission:telawah.addgroup'])->group(function () {
            Route::get('/main/groups/create', [TelawahGroupAdminController::class, 'createReciter'])->name('main.groups.create');
            Route::post('/main/groups', [TelawahGroupAdminController::class, 'storeReciter'])->name('main.groups.store');
        });
        Route::prefix('groups/{telawahGroup}')->whereNumber('telawahGroup')->name('groups.')->group(function () {
            Route::middleware(['admin.permission:telawah.editgroup'])->group(function () {
                Route::get('/edit', [TelawahGroupAdminController::class, 'edit'])->name('edit');
                Route::put('/', [TelawahGroupAdminController::class, 'update'])->name('update');
            });
            Route::middleware(['admin.permission:telawah.addtelawah'])->group(function () {
                Route::get('/items/create', [TelawahGroupAdminController::class, 'createItem'])->name('items.create');
                Route::post('/items', [TelawahGroupAdminController::class, 'storeItem'])->name('items.store');
            });
            Route::middleware(['admin.permission:telawah.addgroup'])->group(function () {
                Route::get('/subgroups/create', [TelawahGroupAdminController::class, 'createSubGroup'])->name('subgroups.create');
                Route::post('/subgroups', [TelawahGroupAdminController::class, 'storeSubGroup'])->name('subgroups.store');
            });
            Route::middleware(['admin.permission:telawah.delgroup'])->group(function () {
                Route::get('/delete', [TelawahGroupAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [TelawahGroupAdminController::class, 'destroy'])->name('destroy');
            });
        });
        Route::prefix('items/{telawahItem}')->whereNumber('telawahItem')->name('items.')->group(function () {
            Route::middleware(['admin.permission:telawah.edittelawah'])->group(function () {
                Route::get('/edit', [TelawahItemAdminController::class, 'edit'])->name('edit');
                Route::put('/', [TelawahItemAdminController::class, 'update'])->name('update');
                Route::get('/move', [TelawahItemAdminController::class, 'move'])->name('move');
                Route::put('/move', [TelawahItemAdminController::class, 'saveMove'])->name('move.save');
            });
            Route::middleware(['admin.permission:telawah.deltelawah'])->group(function () {
                Route::get('/delete', [TelawahItemAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [TelawahItemAdminController::class, 'destroy'])->name('destroy');
            });
        });
        Route::get('/move-options/sub-groups', [TelawahItemAdminController::class, 'moveSubGroups'])
            ->middleware(['admin.permission:telawah.edittelawah'])->name('move-options.sub-groups');
    });

    // OLD public admin parity — Fatwa (owner, 2026-10-30): the fourteen
    // working OLD public controls of the category, topic and question pages
    // (C1, T1, T2, Q1, Q2, Q4, Q5, Q6, A1–A5) and the add / edit forms' size
    // lookup (X2). OLD gated its whole Fatwa admin with one module-level
    // check, so one permission covers all (owner decision 1). The dead C2,
    // T3, Q3, D1–D7 and X1 have no route. Each mutation is a non-GET.
    Route::middleware(['admin.permission:fatwa.manage'])->prefix('fatwa')->name('fatwa.')->group(function () {
        Route::get('/categories/{category}/topics/create', [FatwaTopicAdminController::class, 'createTopic'])->whereNumber('category')->name('categories.topics.create');
        Route::post('/categories/{category}/topics', [FatwaTopicAdminController::class, 'storeTopic'])->whereNumber('category')->name('categories.topics.store');

        Route::prefix('topics/{fatwaTopic}')->whereNumber('fatwaTopic')->name('topics.')->group(function () {
            Route::get('/questions/create', [FatwaTopicAdminController::class, 'createQuestion'])->name('questions.create');
            Route::post('/questions', [FatwaTopicAdminController::class, 'storeQuestion'])->name('questions.store');
            Route::get('/edit', [FatwaTopicAdminController::class, 'edit'])->name('edit');
            Route::put('/', [FatwaTopicAdminController::class, 'update'])->name('update');
        });

        Route::prefix('questions/{fatwaGeneralQuestion}')->whereNumber('fatwaGeneralQuestion')->name('questions.')->group(function () {
            Route::get('/fatwas/create', [FatwaGeneralQuestionAdminController::class, 'createFatwa'])->name('fatwas.create');
            Route::post('/fatwas', [FatwaGeneralQuestionAdminController::class, 'storeFatwa'])->name('fatwas.store');
            Route::get('/edit', [FatwaGeneralQuestionAdminController::class, 'edit'])->name('edit');
            Route::put('/', [FatwaGeneralQuestionAdminController::class, 'update'])->name('update');
            foreach (['add' => 'addTopics', 'move' => 'moveTopic', 'remove' => 'removeTopics'] as $mode => $method) {
                Route::get('/topics/'.$mode, [FatwaGeneralQuestionAdminController::class, 'topics'])->defaults('mode', $mode)->name('topics.'.$mode);
                Route::put('/topics/'.$mode, [FatwaGeneralQuestionAdminController::class, $method])->name('topics.'.$mode.'.save');
            }
        });

        Route::prefix('fatwas/{fatwaQuestion}')->whereNumber('fatwaQuestion')->name('fatwas.')->group(function () {
            Route::get('/edit', [FatwaAdminController::class, 'edit'])->name('edit');
            Route::put('/', [FatwaAdminController::class, 'update'])->name('update');
            Route::get('/delete', [FatwaAdminController::class, 'confirmDestroy'])->name('delete');
            Route::delete('/', [FatwaAdminController::class, 'destroy'])->name('destroy');
            foreach (['add' => 'addQuestions', 'move' => 'moveQuestion', 'remove' => 'removeQuestions'] as $mode => $method) {
                Route::get('/questions/'.$mode, [FatwaAdminController::class, 'questions'])->defaults('mode', $mode)->name('questions.'.$mode);
                Route::put('/questions/'.$mode, [FatwaAdminController::class, $method])->name('questions.'.$mode.'.save');
            }
        });

        Route::get('/media-size', [FatwaAdminController::class, 'mediaSize'])->name('media-size');
    });

    // OLD public admin parity — the Khotab group page's «لوحة تحكم المجموعة»
    // and its per-series / per-item detach icons (owner, 2026-10-21). One
    // route per OLD `admin.php?op=…` step; each mutation is a non-GET.
    // «مواد لم تصنف» (OLD `KhotabLists`) carries no permission: OLD checked
    // nothing there, so it stays open to every administrator (admin.role).
    Route::prefix('khotab')->name('khotab.')->group(function () {
        Route::prefix('groups/{group}')->whereNumber('group')->name('groups.')->group(function () {
            Route::middleware(['admin.permission:khotab.sortitems'])->group(function () {
                Route::get('/rearrange', [KhotabGroupAdminController::class, 'rearrange'])->name('rearrange');
                Route::post('/rearrange', [KhotabGroupAdminController::class, 'saveRearrange'])->name('rearrange.save');
            });
            Route::middleware(['admin.permission:khotab.addcat'])->group(function () {
                Route::get('/fastcopy', [KhotabGroupAdminController::class, 'fastCopy'])->name('fastcopy');
                Route::post('/fastcopy', [KhotabGroupAdminController::class, 'saveFastCopy'])->name('fastcopy.save');
            });
            Route::middleware(['admin.permission:khotab.editgroup'])->group(function () {
                Route::get('/edit', [KhotabGroupAdminController::class, 'edit'])->name('edit');
                Route::put('/', [KhotabGroupAdminController::class, 'update'])->name('update');
            });
            Route::middleware(['admin.permission:khotab.delgroup'])->group(function () {
                Route::get('/delete', [KhotabGroupAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [KhotabGroupAdminController::class, 'destroy'])->name('destroy');
            });
            Route::middleware(['admin.permission:khotab.addseries'])->group(function () {
                Route::get('/series/create', [KhotabGroupAdminController::class, 'createSeries'])->name('series.create');
                Route::post('/series', [KhotabGroupAdminController::class, 'storeSeries'])->name('series.store');
            });
            Route::middleware(['admin.permission:khotab.additem'])->group(function () {
                Route::get('/items/create', [KhotabGroupAdminController::class, 'createItem'])->name('items.create');
                Route::post('/items', [KhotabGroupAdminController::class, 'storeItem'])->name('items.store');
            });
            Route::get('/items', [KhotabGroupAdminController::class, 'items'])->name('items');
        });

        // The Khotab series page's «لوحة تحكم السلسلة» (same owner rule,
        // 2026-10-21); its per-item detach icon reuses items.detach below.
        Route::prefix('series/{series}')->whereNumber('series')->name('series.')->group(function () {
            Route::middleware(['admin.permission:khotab.sortitems'])->group(function () {
                Route::get('/rearrange', [KhotabSeriesAdminController::class, 'rearrange'])->name('rearrange');
                Route::post('/rearrange', [KhotabSeriesAdminController::class, 'saveRearrange'])->name('rearrange.save');
            });
            Route::middleware(['admin.permission:khotab.addcat'])->group(function () {
                Route::get('/fastcopy', [KhotabSeriesAdminController::class, 'fastCopy'])->name('fastcopy');
                Route::post('/fastcopy', [KhotabSeriesAdminController::class, 'saveFastCopy'])->name('fastcopy.save');
            });
            Route::middleware(['admin.permission:khotab.editseries'])->group(function () {
                Route::get('/edit', [KhotabSeriesAdminController::class, 'edit'])->name('edit');
                Route::put('/', [KhotabSeriesAdminController::class, 'update'])->name('update');
            });
            Route::middleware(['admin.permission:khotab.delseries'])->group(function () {
                Route::get('/delete', [KhotabSeriesAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [KhotabSeriesAdminController::class, 'destroy'])->name('destroy');
            });
            Route::middleware(['admin.permission:khotab.additem'])->group(function () {
                Route::get('/items/create', [KhotabSeriesAdminController::class, 'createItem'])->name('items.create');
                Route::post('/items', [KhotabSeriesAdminController::class, 'storeItem'])->name('items.store');
            });
            Route::get('/items', [KhotabSeriesAdminController::class, 'items'])->name('items');
        });

        // Khotab Main's admin panel (OLD Department2() + admincp.htm, same owner
        // rule, 2026-10-21) on khotab-video.htm / khotab-audio.htm; {media}
        // is the page the panel was on. OLD's «ملفات مفقودة» had no handler.
        Route::prefix('main/{media}')->where(['media' => 'video|audio'])->name('main.')->group(function () {
            Route::middleware(['admin.permission:khotab.addauthor'])->group(function () {
                Route::get('/authors/create', [KhotabMainAdminController::class, 'createAuthor'])->name('authors.create');
                Route::post('/authors', [KhotabMainAdminController::class, 'storeAuthor'])->name('authors.store');
            });
            Route::post('/repair-counts', [KhotabMainAdminController::class, 'repairCounts'])
                ->middleware(['admin.permission:khotab.editauthor'])->name('repair-counts');
            Route::middleware(['admin.permission:khotab.approvecomment,khotab.delcomment'])->group(function () {
                Route::get('/comments', [KhotabMainAdminController::class, 'comments'])->name('comments');
                Route::post('/comments', [KhotabMainAdminController::class, 'moderateComments'])->name('comments.moderate');
            });
        });

        // The Khotab author page's «لوحة تحكم الداعية» (same owner rule,
        // 2026-10-21) on khotab-video-{id}.htm / khotab-audio-{id}.htm;
        // {media} is the page the panel was on. «مواد لم تصنف» carries no
        // permission: OLD checked nothing there (as on the group / series pages).
        Route::prefix('authors/{author}/{media}')->where(['author' => '[0-9]+', 'media' => 'video|audio'])->name('authors.')->group(function () {
            Route::middleware(['admin.permission:khotab.editauthor'])->group(function () {
                Route::get('/edit', [KhotabAuthorAdminController::class, 'edit'])->name('edit');
                Route::put('/', [KhotabAuthorAdminController::class, 'update'])->name('update');
                Route::post('/image', [KhotabAuthorAdminController::class, 'uploadImage'])->name('image');
                Route::post('/repair-counts', [KhotabAuthorAdminController::class, 'repairCounts'])->name('repair-counts');
            });
            Route::middleware(['admin.permission:khotab.addgroup'])->group(function () {
                Route::get('/groups/create', [KhotabAuthorAdminController::class, 'createGroup'])->name('groups.create');
                Route::post('/groups', [KhotabAuthorAdminController::class, 'storeGroup'])->name('groups.store');
            });
            Route::middleware(['admin.permission:khotab.addseries'])->group(function () {
                Route::get('/series/create', [KhotabAuthorAdminController::class, 'createSeries'])->name('series.create');
                Route::post('/series', [KhotabAuthorAdminController::class, 'storeSeries'])->name('series.store');
            });
            Route::middleware(['admin.permission:khotab.additem'])->group(function () {
                Route::get('/items/create', [KhotabAuthorAdminController::class, 'createItem'])->name('items.create');
                Route::post('/items', [KhotabAuthorAdminController::class, 'storeItem'])->name('items.store');
            });
            Route::middleware(['admin.permission:khotab.delauthor'])->group(function () {
                Route::get('/delete', [KhotabAuthorAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [KhotabAuthorAdminController::class, 'destroy'])->name('destroy');
            });
            Route::get('/items', [KhotabAuthorAdminController::class, 'items'])->name('items');
        });

        // The Khotab item page's «لوحة تحكم المشرفين» and its comment controls
        // (same owner rule, 2026-10-21), each gated by its real menu key. OLD's
        // GET «نعم» confirmations are forms here.
        Route::prefix('items/{item}')->whereNumber('item')->name('items.')->group(function () {
            Route::middleware(['admin.permission:khotab.edititem'])->group(function () {
                Route::get('/edit', [KhotabItemAdminController::class, 'edit'])->name('edit');
                Route::put('/', [KhotabItemAdminController::class, 'update'])->name('update');
                Route::post('/images', [KhotabItemAdminController::class, 'leechImages'])->name('images');
            });
            Route::middleware(['admin.permission:khotab.moveitem'])->group(function () {
                Route::get('/move', [KhotabItemAdminController::class, 'move'])->name('move');
                Route::put('/move', [KhotabItemAdminController::class, 'saveMove'])->name('move.save');
            });
            Route::middleware(['admin.permission:khotab.delitem'])->group(function () {
                Route::get('/delete', [KhotabItemAdminController::class, 'confirmDestroy'])->name('delete');
                Route::delete('/', [KhotabItemAdminController::class, 'destroy'])->name('destroy');
            });
            Route::middleware(['admin.permission:khotab.uploadbanner'])->group(function () {
                Route::get('/banner', [KhotabItemAdminController::class, 'banner'])->name('banner');
                Route::post('/banner', [KhotabItemAdminController::class, 'uploadBanner'])->name('banner.upload');
                Route::get('/banner/delete', [KhotabItemAdminController::class, 'confirmDeleteBanner'])->name('banner.delete');
                Route::delete('/banner', [KhotabItemAdminController::class, 'deleteBanner'])->name('banner.destroy');
            });
            Route::post('/banner/request', [KhotabItemAdminController::class, 'requestBanner'])
                ->middleware(['admin.permission:khotab.requestbanner'])->name('banner.request');
            Route::middleware(['admin.permission:khotab.uploadpdf'])->group(function () {
                Route::get('/pdf', [KhotabItemAdminController::class, 'fileForm'])->defaults('kind', 'pdf')->name('pdf');
                Route::post('/pdf', [KhotabItemAdminController::class, 'uploadFile'])->defaults('kind', 'pdf')->name('pdf.upload');
                Route::get('/pdf/delete', [KhotabItemAdminController::class, 'confirmDeleteFile'])->defaults('kind', 'pdf')->name('pdf.delete');
                Route::delete('/pdf', [KhotabItemAdminController::class, 'deleteFile'])->defaults('kind', 'pdf')->name('pdf.destroy');
            });
            Route::middleware(['admin.permission:khotab.adddocx'])->group(function () {
                Route::get('/docx', [KhotabItemAdminController::class, 'fileForm'])->defaults('kind', 'docx')->name('docx');
                Route::post('/docx', [KhotabItemAdminController::class, 'uploadFile'])->defaults('kind', 'docx')->name('docx.upload');
            });
            Route::middleware(['admin.permission:khotab.deletedocx'])->group(function () {
                Route::get('/docx/delete', [KhotabItemAdminController::class, 'confirmDeleteFile'])->defaults('kind', 'docx')->name('docx.delete');
                Route::delete('/docx', [KhotabItemAdminController::class, 'deleteFile'])->defaults('kind', 'docx')->name('docx.destroy');
            });
            Route::post('/comments', [KhotabItemAdminController::class, 'storeComment'])
                ->middleware(['admin.permission:khotab.approvecomment'])->name('comments.store');
        });
        Route::middleware(['admin.permission:khotab.moveitem'])->prefix('move-options')->name('move-options.')->group(function () {
            Route::get('/groups', [KhotabItemAdminController::class, 'moveGroups'])->name('groups');
            Route::get('/series', [KhotabItemAdminController::class, 'moveSeries'])->name('series');
        });
        Route::prefix('comments/{comment}')->whereNumber('comment')->name('comments.')->group(function () {
            Route::get('/edit', [KhotabItemAdminController::class, 'editComment'])
                ->middleware(['admin.permission:khotab.approvecomment,khotab.delcomment'])->name('edit');
            Route::put('/', [KhotabItemAdminController::class, 'publishComment'])
                ->middleware(['admin.permission:khotab.approvecomment'])->name('publish');
            Route::post('/hide', [KhotabItemAdminController::class, 'hideComment'])
                ->middleware(['admin.permission:khotab.delcomment'])->name('hide');
        });

        Route::get('/archive-metadata', [KhotabGroupAdminController::class, 'archive'])
            ->middleware(['admin.permission:khotab.additem'])->name('archive');

        Route::middleware(['admin.permission:khotab.moveitem'])->group(function () {
            Route::post('/series/{series}/detach', [KhotabGroupAdminController::class, 'detachSeries'])->whereNumber('series')->name('series.detach');
            Route::post('/items/{item}/detach', [KhotabGroupAdminController::class, 'detachItem'])->whereNumber('item')->name('items.detach');
        });
    });

    // OLD Admin Migration Phase 4 — PHP-Nuke poll administration
    // (`nuke_poll_*`), separate from the custom-survey routes above. OLD
    // gated create / edit / remove with one module-level check, reproduced
    // as polls.managepolls; comment removal was radminsuper-only in OLD,
    // reproduced as the super-admin role.
    Route::prefix('polls')->name('polls.')->group(function () {
        Route::middleware(['admin.permission:polls.managepolls'])->group(function () {
            Route::get('/', [PollAdminController::class, 'index'])->name('index');
            Route::get('/create', [PollAdminController::class, 'create'])->name('create');
            Route::post('/', [PollAdminController::class, 'store'])->name('store');
            Route::get('/{poll}/edit', [PollAdminController::class, 'edit'])->whereNumber('poll')->name('edit');
            Route::put('/{poll}', [PollAdminController::class, 'update'])->whereNumber('poll')->name('update');
            Route::delete('/{poll}', [PollAdminController::class, 'destroy'])->whereNumber('poll')->name('destroy');
        });
        Route::middleware(['admin.role:super-admin'])->group(function () {
            Route::get('/{poll}/comments', [PollAdminController::class, 'comments'])->whereNumber('poll')->name('comments');
            Route::delete('/comments/{comment}', [PollAdminController::class, 'destroyComment'])->whereNumber('comment')->name('comments.destroy');
        });
    });

    // OLD Admin Migration Phase 5 — Featured / Promotional Content
    // (Advfiles). Requesting needs only an authenticated admin, as OLD's
    // KhotabRequestAdvFile did (its GreenZone gate was a no-op); every
    // management step — queue, approve, reject, edit, remove, credit — needs
    // khotab.addadv, the real admincp/khotab/menu.php key.
    Route::prefix('advfiles')->name('advfiles.')->group(function () {
        Route::get('/lookup', [AdvfileController::class, 'lookup'])->name('lookup');
        Route::get('/items/{khotabItem}', [AdvfileController::class, 'item'])->whereNumber('khotabItem')->name('item');
        Route::post('/items/{khotabItem}/request', [AdvfileController::class, 'request'])->whereNumber('khotabItem')->name('request');
        Route::middleware(['admin.permission:khotab.addadv'])->group(function () {
            Route::get('/', [AdvfileController::class, 'queue'])->name('queue');
            Route::get('/items/{khotabItem}/approve', [AdvfileController::class, 'approveForm'])->whereNumber('khotabItem')->name('approve.form');
            Route::post('/items/{khotabItem}/approve', [AdvfileController::class, 'approve'])->whereNumber('khotabItem')->name('approve');
            Route::post('/items/{khotabItem}/reject', [AdvfileController::class, 'reject'])->whereNumber('khotabItem')->name('reject');
            Route::get('/items/{khotabItem}/edit', [AdvfileController::class, 'edit'])->whereNumber('khotabItem')->name('edit');
            Route::put('/items/{khotabItem}', [AdvfileController::class, 'update'])->whereNumber('khotabItem')->name('update');
            Route::delete('/items/{khotabItem}', [AdvfileController::class, 'unpublish'])->whereNumber('khotabItem')->name('unpublish');
            Route::get('/credits', [AdvfileController::class, 'credits'])->name('credits');
            Route::post('/credits', [AdvfileController::class, 'saveCredits'])->name('credits.save');
        });
    });

    // OLD Admin Migration Phase 6 — CD / Album Downloads (W2ACD). One
    // permission, as OLD gated its whole w2acd admin module with one check.
    Route::middleware(['admin.permission:w2acd.managecds'])->prefix('cds')->name('cds.')->group(function () {
        Route::get('/', [CdReleaseController::class, 'index'])->name('index');
        Route::get('/create', [CdReleaseController::class, 'create'])->name('create');
        Route::post('/', [CdReleaseController::class, 'store'])->name('store');
        Route::post('/order', [CdReleaseController::class, 'saveOrder'])->name('order');
        Route::get('/{release}/edit', [CdReleaseController::class, 'edit'])->whereNumber('release')->name('edit');
        Route::put('/{release}', [CdReleaseController::class, 'update'])->whereNumber('release')->name('update');
        Route::delete('/{release}', [CdReleaseController::class, 'destroy'])->whereNumber('release')->name('destroy');
        // W2ACD public admin parity, Phase 1 — the CD page's «رفع بانر» / «تعديل البانر» / «حذف البانر».
        Route::get('/{release}/banner', [CdBannerController::class, 'edit'])->whereNumber('release')->name('banner');
        Route::post('/{release}/banner', [CdBannerController::class, 'upload'])->whereNumber('release')->name('banner.upload');
        Route::get('/{release}/banner/delete', [CdBannerController::class, 'confirmDelete'])->whereNumber('release')->name('banner.delete');
        Route::delete('/{release}/banner', [CdBannerController::class, 'destroy'])->whereNumber('release')->name('banner.destroy');
        // W2ACD public admin parity, Phase 2 — the group page's «اضافة صورة للقسم» / «تعديل القسم» / «إضافة قسم فرعي» / «حذف القسم».
        // W2ACD public admin parity, Phase 3 — M4 on cds-main: a top-level group.
        Route::get('/groups/create', [CdGroupAdminController::class, 'createRootGroup'])->name('groups.create');
        Route::post('/groups', [CdGroupAdminController::class, 'storeRootGroup'])->name('groups.store');
        Route::prefix('groups/{cdGroup}')->whereNumber('cdGroup')->name('groups.')->group(function () {
            Route::get('/icon', [CdGroupAdminController::class, 'icon'])->name('icon');
            Route::post('/icon', [CdGroupAdminController::class, 'uploadIcon'])->name('icon.store');
            Route::get('/edit', [CdGroupAdminController::class, 'edit'])->name('edit');
            Route::put('/', [CdGroupAdminController::class, 'update'])->name('update');
            Route::get('/subgroups/create', [CdGroupAdminController::class, 'createSubGroup'])->name('subgroups.create');
            Route::post('/subgroups', [CdGroupAdminController::class, 'storeSubGroup'])->name('subgroups.store');
            Route::get('/delete', [CdGroupAdminController::class, 'confirmDestroy'])->name('delete');
            Route::delete('/', [CdGroupAdminController::class, 'destroy'])->name('destroy');
        });
    });

    // OLD Admin Migration Phase 7 — Mobile Downloads (قسم الموبايل): staff
    // administration and moderation. One permission, as OLD gated its
    // whole `modules/Mobile/admin` with one module-level check.
    Route::middleware(['admin.permission:mobile.managedownloads'])->prefix('mobile')->name('mobile.')->group(function () {
        Route::get('/', [MobileAdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/recalculate', [MobileAdminController::class, 'recalculate'])->name('recalculate');
        Route::get('/categories', [MobileAdminController::class, 'categories'])->name('categories');
        Route::post('/categories', [MobileAdminController::class, 'storeCategory'])->name('categories.store');
        Route::post('/categories/transfer', [MobileAdminController::class, 'transfer'])->name('categories.transfer');
        Route::get('/categories/{category}/edit', [MobileAdminController::class, 'editCategory'])->whereNumber('category')->name('categories.edit');
        Route::put('/categories/{category}', [MobileAdminController::class, 'updateCategory'])->whereNumber('category')->name('categories.update');
        Route::delete('/categories/{category}', [MobileAdminController::class, 'destroyCategory'])->whereNumber('category')->name('categories.destroy');
        Route::get('/materials', [MobileAdminController::class, 'materials'])->name('materials');
        Route::get('/materials/create', [MobileAdminController::class, 'createMaterial'])->name('materials.create');
        Route::post('/materials', [MobileAdminController::class, 'storeMaterial'])->name('materials.store');
        Route::get('/materials/{material}/edit', [MobileAdminController::class, 'editMaterial'])->whereNumber('material')->name('materials.edit');
        Route::put('/materials/{material}', [MobileAdminController::class, 'updateMaterial'])->whereNumber('material')->name('materials.update');
        Route::delete('/materials/{material}', [MobileAdminController::class, 'destroyMaterial'])->whereNumber('material')->name('materials.destroy');
        Route::put('/materials/{material}/editorial', [MobileAdminController::class, 'saveEditorial'])->whereNumber('material')->name('materials.editorial');
        Route::delete('/materials/{material}/editorial', [MobileAdminController::class, 'destroyEditorial'])->whereNumber('material')->name('materials.editorial.destroy');
        Route::delete('/votes/{vote}', [MobileAdminController::class, 'destroyVote'])->whereNumber('vote')->name('votes.destroy');
        Route::delete('/votes/{vote}/comment', [MobileAdminController::class, 'destroyComment'])->whereNumber('vote')->name('votes.comment.destroy');
        Route::get('/submissions', [MobileAdminController::class, 'submissions'])->name('submissions');
        Route::post('/submissions/{submission}/approve', [MobileAdminController::class, 'approveSubmission'])->whereNumber('submission')->name('submissions.approve');
        Route::delete('/submissions/{submission}', [MobileAdminController::class, 'rejectSubmission'])->whereNumber('submission')->name('submissions.reject');
        Route::get('/broken', [MobileAdminController::class, 'brokenReports'])->name('broken');
        Route::delete('/broken/{lid}/material', [MobileAdminController::class, 'destroyReportedMaterial'])->whereNumber('lid')->name('broken.destroy-material');
        Route::delete('/broken/{lid}', [MobileAdminController::class, 'ignoreBroken'])->whereNumber('lid')->name('broken.ignore');
        Route::get('/corrections', [MobileAdminController::class, 'corrections'])->name('corrections');
        Route::post('/corrections/{changeRequest}/accept', [MobileAdminController::class, 'acceptCorrection'])->whereNumber('changeRequest')->name('corrections.accept');
        Route::delete('/corrections/{changeRequest}', [MobileAdminController::class, 'ignoreCorrection'])->whereNumber('changeRequest')->name('corrections.ignore');
    });

    // Super-admin only, matching OLD's listlogs(), which returned nothing
    // unless radminsuper === 1. Same role gate as the permission editor.
    Route::middleware(['admin.role:super-admin'])->prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AdminAuditLogController::class, 'index'])->name('index');
        Route::get('/legacy', [AdminAuditLogController::class, 'legacy'])->name('legacy');
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

// OLD public admin parity — `anasheed_without_cats.htm` (OLD
// `anasheed_without_cats()`, `.htaccess:494/497`): an admin-only page at OLD's
// own public URL, so it sits outside the `/admincp` prefix but behind the same
// admin middleware (owner decision 2026-10-29: `anasheed.addcat`).
Route::middleware(['admin.role', 'admin.permission:anasheed.addcat'])->name('admin.anasheed.')->group(function () {
    Route::get('/anasheed_without_cats.htm', [AnasheedWithoutCatsController::class, 'show'])->name('without-cats');
    Route::get('/anasheed_without_cats-page-{page}.htm', [AnasheedWithoutCatsController::class, 'show'])
        ->whereNumber('page')->name('without-cats.paged');
});

// OLD public admin parity — `anasheed_without_authors.htm` (OLD
// `anasheed_without_authors()`, `.htaccess:501/504`): same placement as the
// list above; owner decision 2026-10-29: `anasheed.editanasheed`, the
// permission of the T4 edit it opens.
Route::middleware(['admin.role', 'admin.permission:anasheed.editanasheed'])->name('admin.anasheed.')->group(function () {
    Route::get('/anasheed_without_authors.htm', [AnasheedWithoutAuthorsController::class, 'show'])->name('without-authors');
    Route::get('/anasheed_without_authors-page-{page}.htm', [AnasheedWithoutAuthorsController::class, 'show'])
        ->whereNumber('page')->name('without-authors.paged');
});

// OLD public admin parity — `anasheed_without_channels.htm` (OLD
// `anasheed_without_channels()`, `.htaccess:507/510`): same placement and
// permission as the list above (owner decision 2026-10-29).
Route::middleware(['admin.role', 'admin.permission:anasheed.editanasheed'])->name('admin.anasheed.')->group(function () {
    Route::get('/anasheed_without_channels.htm', [AnasheedWithoutChannelsController::class, 'show'])->name('without-channels');
    Route::get('/anasheed_without_channels-page-{page}.htm', [AnasheedWithoutChannelsController::class, 'show'])
        ->whereNumber('page')->name('without-channels.paged');
});
