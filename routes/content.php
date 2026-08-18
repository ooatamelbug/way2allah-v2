<?php

use App\Domain\Content\Http\Controllers\AnasheedGroupController;
use App\Domain\Content\Http\Controllers\AnasheedItemController;
use App\Domain\Content\Http\Controllers\AnasheedNewsController;
use App\Domain\Content\Http\Controllers\CategoryController;
use App\Domain\Content\Http\Controllers\CategoryDownItemsController;
use App\Domain\Content\Http\Controllers\CategorySeriesController;
use App\Domain\Content\Http\Controllers\CategoryTreeController;
use App\Domain\Content\Http\Controllers\ChannelController;
use App\Domain\Content\Http\Controllers\ChatRoomLessonController;
use App\Domain\Content\Http\Controllers\FatwaAuthorController;
use App\Domain\Content\Http\Controllers\FatwaByAuthorsController;
use App\Domain\Content\Http\Controllers\FatwaChannelController;
use App\Domain\Content\Http\Controllers\FatwaDayController;
use App\Domain\Content\Http\Controllers\FatwaLatestController;
use App\Domain\Content\Http\Controllers\FatwaQuestionController;
use App\Domain\Content\Http\Controllers\FatwaTopicController;
use App\Domain\Content\Http\Controllers\GalleryController;
use App\Domain\Content\Http\Controllers\KhotabAuthorController;
use App\Domain\Content\Http\Controllers\KhotabDayController;
use App\Domain\Content\Http\Controllers\KhotabDumpController;
use App\Domain\Content\Http\Controllers\KhotabGroupController;
use App\Domain\Content\Http\Controllers\KhotabItemController;
use App\Domain\Content\Http\Controllers\LocationController;
use App\Domain\Content\Http\Controllers\KhotabNewsController;
use App\Domain\Content\Http\Controllers\KhotabSearchController;
use App\Domain\Content\Http\Controllers\KhotabSeriesController;
use App\Domain\Content\Http\Controllers\LiveStreamController;
use App\Domain\Content\Http\Controllers\MediaPlayerController;
use App\Domain\Content\Http\Controllers\RadioController;
use App\Domain\Content\Http\Controllers\SearchController;
use App\Domain\Content\Http\Controllers\TelawahAuthorController;
use App\Domain\Content\Http\Controllers\TelawahGroupController;
use App\Domain\Content\Http\Controllers\TelawahItemController;
use App\Domain\Content\Http\Controllers\W2acdController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Content Routes (Blueprint v1.0 §7, Roadmap task 3.2)
|--------------------------------------------------------------------------
|
| live-stream.htm / live-channel-{id}.htm / channels.htm / channel-{id}.htm /
| channel-{id}-{id2}.htm are REAL, LIVE, currently-indexed .htaccess rules
| (confirmed via .htaccess, live-stream.md §2 / channels.md §2) — unlike
| Wave 2's pages, these keep their EXACT legacy path here rather than going
| through LegacyUrlCompatibility's redirect mechanism, per Blueprint §11:
| "the equivalent Laravel route (transparently, no redirect, for URLs that
| keep their exact path)". Only live.php (no .htaccess rule at all) gets a
| new path + a legacy-path redirect, same pattern as Wave 2.
|
*/

Route::get('/live-stream.htm', [LiveStreamController::class, 'index'])->name('live-stream.index');
Route::get('/live-channel-{channel}.htm', [LiveStreamController::class, 'show'])
    ->whereNumber('channel')
    ->name('live-stream.show');
Route::get('/live-stream/featured', [LiveStreamController::class, 'featured'])->name('live-stream.featured');

Route::get('/channels.htm', [ChannelController::class, 'index'])->name('channels.index');
Route::get('/channel-{channel}.htm', [ChannelController::class, 'show'])
    ->whereNumber('channel')
    ->name('channels.show');
Route::get('/channel-{channel}-{author}.htm', [ChannelController::class, 'showAuthor'])
    ->whereNumber(['channel', 'author'])
    ->name('channels.show-author');

/*
| khotab-item-{id}.htm / khotab-download-{id}.htm / khotab-mirror-{id}-{id2}.htm
| are real, live .htaccess rules (khotab/item.php, confirmed) — kept at
| their exact legacy path, same rationale as above.
|
| khotab-item-pdf-{id}.htm is IF-020's fix: this exact path is what
| item.php:210 already links to, but no .htaccess rule ever backed it
| (confirmed by exhaustive .htaccess search) — it 404s in production
| today. Adding a real route at the same path items already link to is
| the fix, not a new URL scheme.
*/
Route::get('/khotab-item-{khotab}.htm', [KhotabItemController::class, 'show'])
    ->whereNumber('khotab')
    ->name('khotab.item.show');
Route::get('/khotab-download-{khotab}.htm', [KhotabItemController::class, 'download'])
    ->whereNumber('khotab')
    ->name('khotab.item.download');
Route::get('/khotab-mirror-{khotab}-{mirror}.htm', [KhotabItemController::class, 'downloadMirror'])
    ->whereNumber(['khotab', 'mirror'])
    ->name('khotab.item.download-mirror');
Route::get('/khotab-item-pdf-{khotab}.htm', [KhotabItemController::class, 'downloadPdf'])
    ->whereNumber('khotab')
    ->name('khotab.item.download-pdf');
Route::post('/khotab-item-{khotab}/comments', [KhotabItemController::class, 'storeComment'])
    ->whereNumber('khotab')
    ->name('khotab.item.store-comment');
/*
| Visual parity audit (khotab-item-298784.htm) Batch 3 / Finding #11 —
| khotab_send_friend()'s own AJAX endpoint (`send-friend-anasheed-{id}.htm`
| in legacy's actual, cross-module-buggy `anasheed_scripts.js`, per
| KhotabFriendMail's docblock) is not reproduced at that URL; this is a
| Laravel-native route, same URL-adaptation approach already established
| for /khotab-item-{khotab}/comments above.
*/
Route::post('/khotab-item-{khotab}/send-friend', [KhotabItemController::class, 'sendToFriend'])
    ->whereNumber('khotab')
    ->name('khotab.item.send-friend');

/*
| Batch 4 (media player, khotab-item-298784.htm investigation) —
| replaces `get-mada-player.htm` (ajax_3K2r.php?op=get-mada-player). Not
| under /khotab-* — confirmed shared, cross-module infrastructure (also
| called by anasheed/telawah/fatawa/chat_room, per the investigation's
| caller matrix), one endpoint per MediaPlayerController's own docblock,
| not duplicated per module/type. Only wired into khotab's frontend this
| batch — the route itself is already reusable as-is.
*/
Route::post('/media-player', [MediaPlayerController::class, 'show'])->name('media-player.show');

/*
| khotab-{video|audio|pdf}.htm / khotab-{video|audio|pdf}-{author}.htm /
| khotab-group-{id}.htm / khotab-series-{id}.htm / khotab-{video|audio}-today.htm /
| khotab-{video|audio}date-{d}-{m}-{y}.htm / khotab-{video|audio|pdf}_news.htm
| are all real, live .htaccess rules — kept at their exact legacy path.
*/
Route::get('/khotab-{op}.htm', [KhotabAuthorController::class, 'index'])
    ->where('op', 'video|audio|pdf')
    ->name('khotab.authors.index');
Route::get('/khotab-{op}-{author}.htm', [KhotabAuthorController::class, 'show'])
    ->where('op', 'video|audio|pdf')
    ->whereNumber('author')
    ->name('khotab.authors.show');

Route::get('/khotab-group-{group}.htm', [KhotabGroupController::class, 'show'])
    ->whereNumber('group')
    ->name('khotab.group.show');
Route::get('/khotab-series-{series}.htm', [KhotabSeriesController::class, 'show'])
    ->whereNumber('series')
    ->name('khotab.series.show');

Route::get('/khotab-video-today.htm', [KhotabDayController::class, 'videoToday'])->name('khotab.day.video-today');
Route::get('/khotab-audio-today.htm', [KhotabDayController::class, 'audioToday'])->name('khotab.day.audio-today');
Route::get('/khotab-videodate-{d}-{m}-{y}.htm', [KhotabDayController::class, 'videoByDate'])
    ->whereNumber(['d', 'm', 'y'])
    ->name('khotab.day.video-date');
Route::get('/khotab-audiodate-{d}-{m}-{y}.htm', [KhotabDayController::class, 'audioByDate'])
    ->whereNumber(['d', 'm', 'y'])
    ->name('khotab.day.audio-date');

Route::get('/khotab-{op}_news.htm', [KhotabNewsController::class, 'show'])
    ->where('op', 'video|audio|pdf')
    ->name('khotab.news.show');

Route::get('/khotab/dump', [KhotabDumpController::class, 'index'])->name('khotab.dump.index');

// G-12-04 (G-12 investigation): dumped-lectures.htm IS a real, live .htaccess
// rule (`.htaccess:221`, `new_modules.php?name=Dump_files&op=Dump_files`) with
// a real, live homepage link (home_functions.php:398) — the "no .htaccess
// rule at all" comment previously here was factually wrong. Same controller
// as /khotab/dump above, registered at its own additional pretty path.
Route::get('/dumped-lectures.htm', [KhotabDumpController::class, 'index'])->name('khotab.dump.pretty');

// khotab/search.php has no .htaccess rule at all (confirmed, IF-018's
// evidence) — same raw-path-only profile as khotab/dump.php above.
Route::get('/khotab/search', [KhotabSearchController::class, 'index'])->name('khotab.search');

// G-09-01 (Phase 1 audit): video-advanced-search.htm is the real pretty
// URL — header.php:259's sitewide "المرئيات" nav dropdown links here
// unconditionally, alongside categories.htm/khotab-video.htm/channels.htm/
// khotab-video-today.htm (all already-migrated). No .htaccess rule ever
// existed for it either, but real standing site chrome linking to a
// pretty path is this project's own established "pattern 3" (decision-log
// #8) — registered at that exact path, same controller action as
// /khotab/search above (not a duplicate implementation).
Route::get('/video-advanced-search.htm', [KhotabSearchController::class, 'index'])->name('khotab.search.pretty');

// categories.htm is a real, live .htaccess rule (categories/tree.php,
// default/op-less branch) — confirmed via the Evidence Reconciliation
// pass (real file, not a dead dispatcher; nuke_w2a_cat confirmed present
// with real data). Only this default branch is built here — tree.php's
// op=fatawa/op=var branches back the separately-scoped
// fatawa-categories.htm/var-categories.htm URLs, not folded in silently.
Route::get('/categories.htm', [CategoryTreeController::class, 'index'])
    ->name('categories.tree');

// category-{id}.htm is a real, live .htaccess rule (categories/category.php,
// Roadmap task 4.3).
Route::get('/category-{category}.htm', [CategoryController::class, 'show'])
    ->whereNumber('category')
    ->name('categories.show');

// category-series-{ser_id}-{cat_id}.htm is a real, live .htaccess rule
// (categories/series.php, .htaccess:175 — URL segment order is ser_id
// first, cat_id second, matching the rule's own `?ser_id=$1&cat_id=$2`).
// Previously deferred only pending khotab_category_index (now populated,
// var-category/categories.htm closure rounds) — see CategorySeriesController.
Route::get('/category-series-{series}-{category}.htm', [CategorySeriesController::class, 'show'])
    ->whereNumber(['series', 'category'])
    ->name('categories.series');

// khotab-series-{id}-{cat}.grx / khotab-series-{id}.grx are real, live
// .htaccess rules (categories/downitems.php, .htaccess:226-227) — a
// GetRight download-playlist format, not an HTML page. See
// CategoryDownItemsController's own docblock for the full DownItems()
// reproduction (windows-1256 output encoding, title sanitization order).
Route::get('/khotab-series-{series}-{category}.grx', [CategoryDownItemsController::class, 'show'])
    ->whereNumber(['series', 'category'])
    ->name('categories.down-items.by-category');
Route::get('/khotab-series-{series}.grx', [CategoryDownItemsController::class, 'show'])
    ->whereNumber('series')
    ->defaults('category', null)
    ->name('categories.down-items');

// var-category-{id}.htm is a real, live .htaccess rule
// (categories/category.php?op=var, .htaccess:174) — confirmed via the
// Evidence Reconciliation pass (live-tested, 200, real content) — a
// distinct URL family from the already-redirected plural
// vars-category-{id}.htm below (categories/category.php?op=var vs.
// vars_categories/category.php, two unrelated legacy files).
Route::get('/var-category-{category}.htm', [CategoryController::class, 'showAnasheed'])
    ->whereNumber('category')
    ->name('categories.show-anasheed');

// var-categories.htm is a real, live .htaccess rule (categories/tree.php
// ?op=var, .htaccess:109) — confirmed via the Next Migration Target
// Analysis (live-tested, 200, real content; nuke_w2a_cat confirmed
// present, 300 rows with anasheed_count>0). Every link this tree
// generates resolves to the already-implemented var-category-{id}.htm
// above. fatawa-categories.htm (tree.php?op=fatawa) is NOT built —
// genuinely blocked: its own generated links target
// fatawa-category-{id}.htm -> fatawa/category.php, a file confirmed
// absent from the entire codebase, not a reachability judgment call.
Route::get('/var-categories.htm', [CategoryTreeController::class, 'varIndex'])
    ->name('categories.tree-anasheed');

// vars_categories/ — IF-031/IF-043: confirmed, by direct read of all 3
// legacy files (not re-trusting the prior claim), to be an older,
// superseded duplicate of categories/ — vars_categories/tree.php's
// op-less branch queries the same nuke_w2a_cat table with the same
// video_count field and category- link slug as categories/tree.php's own
// default branch; vars_categories/series.php calls the identical
// ListKhotab()/Cat_Breadcrumb()/Ser_Cat_Breadcrumb() sequence as
// categories/series.php, byte-for-byte the same behavior. All 3 close as
// redirects to their categories/ equivalents, not new controllers.
// vars-categories.htm and vars-category-series-{id}-{id2}.htm were
// deferred in IF-031 only because their destination routes
// (categories.htm, category-series-{ser_id}-{cat_id}.htm) didn't exist
// yet — both are now VERIFIED AND CLOSED, so the blocker is gone (IF-043).
Route::redirect('/vars-category-{category}.htm', '/category-{category}.htm')
    ->whereNumber('category')
    ->name('categories.vars-redirect');
Route::redirect('/vars-categories.htm', '/categories.htm')
    ->name('categories.vars-tree-redirect');
Route::redirect('/vars-category-series-{series}-{category}.htm', '/category-series-{series}-{category}.htm')
    ->whereNumber(['series', 'category'])
    ->name('categories.vars-series-redirect');

// w2acd — Roadmap task 4.5. IF-026: none of this module's pretty
// cds-*.htm URLs actually reach these files (they all route to a
// nonexistent new_modules.php) — registered at the exact raw legacy
// path instead, since Route::redirect() can't forward the query-string
// ids (?id=, ?khid=) these pages' identity actually lives in.
Route::get('/w2acd/cds.php', [W2acdController::class, 'index'])->name('w2acd.index');
Route::get('/w2acd/item.php', [W2acdController::class, 'show'])->name('w2acd.show');

// G-11-01 (Phase 1 audit): cds-main.htm is a real, permanent sitewide
// nav link (header.php's own "إسطوانات دعوية" dropdown item, reproduced
// verbatim in navigation.blade.php) — unlike the other cds-*.htm URLs
// above, this one has real standing site chrome linking to it, matching
// this project's own established "pattern 3" (decision-log #8), same
// precedent as G-09's video-advanced-search.htm. .htaccess's own rule
// (^cds-main.htm -> new_modules.php?name=w2acd) carries no query string
// at all, so it maps to the exact same default (group id 0) behavior
// W2acdController::index() already serves — no new controller logic.
Route::get('/cds-main.htm', [W2acdController::class, 'index'])->name('w2acd.index.pretty');

// gallery — Roadmap task 4.6. gallery.htm / gallery-{id}.htm /
// albumimg-download-{id}.htm are real, live .htaccess rules (unlike
// w2acd's cds-*.htm set) — kept at their exact legacy path.
Route::get('/gallery.htm', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/gallery-{album}.htm', [GalleryController::class, 'show'])
    ->whereNumber('album')
    ->name('gallery.show');
Route::get('/albumimg-download-{image}.htm', [GalleryController::class, 'download'])
    ->whereNumber('image')
    ->name('gallery.download');

// anasheed — Roadmap task 4.7. var-item-{id}.htm / var-group-{id}.htm /
// var-download-{id}.htm / var-mirror-{id}-{id2}.htm are real, live
// .htaccess rules — kept at their exact legacy path.
Route::get('/var-item-{anasheed}.htm', [AnasheedItemController::class, 'show'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.show');
// G-12-01 (G-12 investigation): var-item-{id}-page-{page}.htm is a real,
// live .htaccess rule (.htaccess:104) — see AnasheedItemController::show()'s
// own docblock for the confirmed legacy double-decrement bug this
// deliberately does NOT reproduce.
Route::get('/var-item-{anasheed}-page-{page}.htm', [AnasheedItemController::class, 'show'])
    ->whereNumber(['anasheed', 'page'])
    ->name('anasheed.item.show.paged');
Route::get('/var-download-{anasheed}.htm', [AnasheedItemController::class, 'download'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.download');
Route::get('/var-mirror-{anasheed}-{mirror}.htm', [AnasheedItemController::class, 'downloadMirror'])
    ->whereNumber(['anasheed', 'mirror'])
    ->name('anasheed.item.download-mirror');
Route::post('/var-item-{anasheed}/comments', [AnasheedItemController::class, 'storeComment'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.store-comment');

// G-11-02 (Phase 1 audit): send-friend-anasheed-{id}.htm is a real, live
// .htaccess rule (anasheed/item.php?op=send_friend) with real, complete
// surviving source (anasheed/functions.php:647-677) — kept at its exact
// legacy path, same as the routes above. The legacy form
// (send_friend_modal()) posts to itself, so this is POST-only, matching
// storeComment()'s own convention.
Route::post('/send-friend-anasheed-{anasheed}.htm', [AnasheedItemController::class, 'sendToFriend'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.send-to-friend');

Route::get('/var-group-{group}.htm', [AnasheedGroupController::class, 'show'])
    ->whereNumber('group')
    ->name('anasheed.group.show');
// G-12-01 (G-12 investigation): var-group-{id}-page-{page}.htm is a real,
// live .htaccess rule (.htaccess:98) — see AnasheedGroupController's own
// docblock for the confirmed real pagination-link generation this restores.
Route::get('/var-group-{group}-page-{page}.htm', [AnasheedGroupController::class, 'show'])
    ->whereNumber(['group', 'page'])
    ->name('anasheed.group.show.paged');

// var-series-{id}.grx is a real, live .htaccess rule
// (anasheed/group.php?op=down_serious, .htaccess:100) — a GetRight
// download-playlist format, not an HTML page. See
// AnasheedGroupController::downloadGetright()'s own docblock for the
// full download_var_group_getright() reproduction — confirmed genuinely
// different from CategoryDownItemsController's khotab .grx generator
// (no windows-1256 re-encode, site-relative download URLs, 2-level
// folder path), not assumed from that sibling.
Route::get('/var-series-{group}.grx', [AnasheedGroupController::class, 'downloadGetright'])
    ->whereNumber('group')
    ->name('anasheed.group.download-getright');

// Wave 5, task 5.1. IF-029's fix — vars/more.php's 4 real, live themed
// routes (previously fatal-erroring), kept at their exact legacy paths.
Route::get('/exclusive-news.htm', [AnasheedNewsController::class, 'show'])
    ->defaults('theme', 'exclusive')->name('anasheed.news.exclusive');
Route::get('/cartoon-news.htm', [AnasheedNewsController::class, 'show'])
    ->defaults('theme', 'cartoon')->name('anasheed.news.cartoon');
Route::get('/documentary-news.htm', [AnasheedNewsController::class, 'show'])
    ->defaults('theme', 'documentary')->name('anasheed.news.documentary');
Route::get('/anasheed-news.htm', [AnasheedNewsController::class, 'show'])
    ->defaults('theme', 'anasheed')->name('anasheed.news.anasheed');

// telawah — Roadmap task 4.8. recite.htm / recite-group-{id}.htm /
// recite-item-{id}.htm / recite-download-{id}.htm are real, live
// .htaccess rules — kept at their exact legacy path.
Route::get('/recite.htm', [TelawahAuthorController::class, 'index'])->name('telawah.authors.index');
Route::get('/recite-group-{group}.htm', [TelawahGroupController::class, 'show'])
    ->whereNumber('group')
    ->name('telawah.group.show');
Route::get('/recite-item-{telawah}.htm', [TelawahItemController::class, 'show'])
    ->whereNumber('telawah')
    ->name('telawah.item.show');
Route::get('/recite-download-{telawah}.htm', [TelawahItemController::class, 'download'])
    ->whereNumber('telawah')
    ->name('telawah.item.download');

// radio — Roadmap task 4.10 (added post-Wave-4, see
// docs/reviews/gap-closure-action-plan.md item 2). radio.htm /
// radio-mobile.htm both route to the same page (radio/index.php,
// confirmed the only file any live .htaccess rule actually reaches — see
// RadioController's docblock and IF-032 for the 3 dead-code op-routes
// that are deliberately NOT reproduced here).
Route::get('/radio.htm', [RadioController::class, 'index'])->name('radio.index');
Route::get('/radio-mobile.htm', [RadioController::class, 'index'])->name('radio.index.mobile');

// chat_room's lesson-browsing half — Roadmap task 4.11 (added post-Wave-4,
// see docs/reviews/gap-closure-action-plan.md item 4). chat_author_{id}.htm
// / chat_lesson_{id}.htm / lesson-download-{id}.htm are real, live
// .htaccess rules — kept at their exact legacy path. chat_room.htm and
// chat_{id}.htm (the live voice-room half of this same legacy directory)
// are NOT part of this task — see ChatRoomLessonController's docblock;
// that half stays task 6.5, gated on Business Confirmation #4.
Route::get('/chat_author_{author}.htm', [ChatRoomLessonController::class, 'author'])
    ->whereNumber('author')
    ->name('chat-room.author');
Route::get('/chat_lesson_{lesson}.htm', [ChatRoomLessonController::class, 'show'])
    ->whereNumber('lesson')
    ->name('chat-room.lesson.show');
Route::get('/lesson-download-{lesson}.htm', [ChatRoomLessonController::class, 'download'])
    ->whereNumber('lesson')
    ->name('chat-room.lesson.download');

// Locations — Wave C ("Public Locations & Da'wah Registration Surfaces").
// .htaccess:152-162's "#Locations" block. Scoped per the Wave C analysis +
// decision round: location-{id}.htm / location-{id}-author-{id2}.htm /
// location-{id}-item-{id2}.htm / alhedaya-room.htm only. locations.htm
// (PARTIALLY RECOVERABLE — no proven public template), location-{id}-
// group-{id2}.htm, location-{id}-series-{id2}.htm (both OPEN / SOURCE
// UNRECOVERABLE), and questionnaire.htm (OPEN / SOURCE UNRECOVERABLE) are
// deliberately NOT registered — see IF-047 through IF-049.
//
// location-{id}-author-{id2}.htm: re-verification (not the original Wave C
// report's assumption) found `chat_room/author.php` — the file this op
// would reach — never reads $_GET['location'] at all; `$location_id = 10`
// is unconditionally hardcoded. This route is therefore NOT a generic
// "any location's author page" — it is byte-for-byte the same output as
// the already-built chat_author_{id}.htm, for every location value. Routed
// through LocationController::locationAuthor(), a thin pass-through to the
// real, unmodified ChatRoomLessonController::author() — not a new query
// (IF-048). Necessary because Laravel binds a route's "extra" (unnamed-in-
// the-target-method) segments positionally, not by name — declaring both
// {location}/{author} explicitly here is what makes {author} actually
// reach ChatRoomLessonController::author()'s $author parameter correctly.
Route::get('/location-{location}.htm', [LocationController::class, 'show'])
    ->whereNumber('location')
    ->name('locations.show');
Route::get('/alhedaya-room.htm', [LocationController::class, 'show'])
    ->defaults('location', 10)
    ->name('locations.alhedaya');
Route::get('/location-{location}-author-{author}.htm', [LocationController::class, 'locationAuthor'])
    ->whereNumber(['location', 'author'])
    ->name('locations.author');

// location-{id}-item-{id2}.htm routes through Khotab, not Locations
// (.htaccess:158, name=Khotab&op=Detailes) — khotab/item.php never reads
// $_GET['location'] either (confirmed), so this is the exact same page as
// khotab-item-{id}.htm. Routed through LocationController::locationItem(),
// the same pass-through-binding fix as locationAuthor() above, delegating
// to the real, unmodified KhotabItemController::show().
Route::get('/location-{location}-item-{khotab}.htm', [LocationController::class, 'locationItem'])
    ->whereNumber(['location', 'khotab'])
    ->name('locations.item');

// fatawa — Roadmap task 6.1 (see the approved technical plan and
// decision-log for the full record). Every .htaccess rule for this
// module targets a missing modules.php dispatcher, not a live redirect —
// routes below are registered at the exact pretty-URL paths .htaccess
// already defines, per the technical plan's "pattern 1, broken target"
// extension (a new precedent, not an application of an existing one).
// Every route's parameter shape below was re-verified directly against
// the literal .htaccess rule text (not fatawa.md's summary table) before
// being registered — increment 1 shipped two of these with an incorrect
// shape (fatawa-topics missing its required page parameter, fatawa-group's
// two parameters in the wrong order), both corrected in increment 2.
//
// fatawa-authors.htm reuses KhotabAuthorController::index() directly —
// its `fatwa` branch was already built during task 4.1 in anticipation of
// this reuse; only the route registration was missing.
//
// fatawa-categories.htm (categories/tree.php?op=fatawa) — the tree page's
// own legacy source is complete (same showtree() already ported for
// categories.htm/var-categories.htm) and is built below.
//
// STILL NOT registered, genuinely blocked, not a scope choice:
// fatawa-category-{id}.htm — its legacy source (fatawa/category.php) is
// confirmed unrecoverable (git history checked, exhaustive codebase
// search performed, no trace found anywhere — see IF-038, Fatawa
// Categories Source Recovery pass). The tree page below will generate
// real fatawa-category-{id}.htm links that do not resolve — a known,
// documented, separate open item, not a defect in the tree page itself.
// No redirect or invented replacement is registered for it.
Route::get('/fatawa-categories.htm', [CategoryTreeController::class, 'fatawaIndex'])
    ->name('categories.tree-fatawa');

Route::get('/fatawa-authors.htm', [KhotabAuthorController::class, 'index'])
    ->defaults('op', 'fatwa')
    ->name('fatawa.authors.index');

Route::get('/fatawa.htm', [FatwaTopicController::class, 'index'])->name('fatawa.topics.index');
Route::get('/fatawa-topics-{category}-{page}.htm', [FatwaTopicController::class, 'show'])
    ->whereNumber(['category', 'page'])
    ->name('fatawa.topics.show');
// .htaccess:301-302 — two real rules, t_id first, cat_id second (page
// defaults to 1 in the 2-parameter form).
Route::get('/fatawa-group-{topic}-{category}.htm', [FatwaTopicController::class, 'questions'])
    ->whereNumber(['topic', 'category'])
    ->defaults('page', 1)
    ->name('fatawa.topics.questions');
Route::get('/fatawa-group-{topic}-{category}-{page}.htm', [FatwaTopicController::class, 'questions'])
    ->whereNumber(['topic', 'category', 'page'])
    ->name('fatawa.topics.questions.paged');

Route::get('/fatawa-{question}.htm', [FatwaQuestionController::class, 'show'])
    ->whereNumber('question')
    ->name('fatawa.question.show');
Route::get('/fatawa-all-{generalQuestion}.htm', [FatwaQuestionController::class, 'showAll'])
    ->whereNumber('generalQuestion')
    ->name('fatawa.question.show-all');
Route::get('/fatawa-download-{question}.htm', [FatwaQuestionController::class, 'download'])
    ->whereNumber('question')
    ->name('fatawa.question.download');

// fatawa, increment 2 — channel browsing, per-author list, latest-50.
// Route shapes re-verified directly against .htaccess:288-298,309 (see
// fatawa.md's increment-2 addendum for the full literal mapping) before
// registration, not inferred from fatawa.md's earlier summary table.
Route::get('/fatawa-channels.htm', [FatwaChannelController::class, 'index'])
    ->defaults('page', 1)
    ->name('fatawa.channels.index');
Route::get('/fatawa-channels-{page}.htm', [FatwaChannelController::class, 'index'])
    ->whereNumber('page')
    ->name('fatawa.channels.index.paged');

Route::get('/fatawa-channel-{channel}.htm', [FatwaChannelController::class, 'show'])
    ->whereNumber('channel')
    ->defaults('page', 1)
    ->name('fatawa.channel.show');
Route::get('/fatawa-channel-{channel}-{page}.htm', [FatwaChannelController::class, 'show'])
    ->whereNumber(['channel', 'page'])
    ->name('fatawa.channel.show.paged');

Route::get('/auther-questions-{author}.htm', [FatwaAuthorController::class, 'show'])
    ->whereNumber('author')
    ->defaults('page', 1)
    ->name('fatawa.author.show');
Route::get('/auther-questions-{author}-{page}.htm', [FatwaAuthorController::class, 'show'])
    ->whereNumber(['author', 'page'])
    ->name('fatawa.author.show.paged');

Route::get('/more-fatawa.htm', [FatwaLatestController::class, 'index'])->name('fatawa.latest');

// fatawa-by-authers.htm — G-07-03 (Phase 1 audit, decision-log/IF entries
// pending). .htaccess:279 routes through the missing modules.php
// dispatcher with op=fatawa_by_authers — that op value never matches
// fatawa-by-authers.php's own internal video/audio/pdf checks, so only its
// default (fatwa) branch is reachable via this route. See
// FatwaByAuthorsController's own docblock.
Route::get('/fatawa-by-authers.htm', [FatwaByAuthorsController::class, 'index'])
    ->name('fatawa.by-authors.index');

// fatawa, increment 3 — day-based browse and send-to-friend.
// fatwa-date-{d}-{m}-{y}-{page}.htm (.htaccess:285) is deliberately NOT
// registered — re-read of fatwa-today.php confirms it never reads
// $_GET['d']/['m']/['y'], only a single $_GET['date'] string that neither
// confirmed route below actually supplies. Same "real rule, no confirmed
// implementing code" category as fatawa-play-*/fatawa-brokenlink-*/
// auther-all-fatawa-* — see FatwaDayController's docblock.
Route::get('/fatwa-today.htm', [FatwaDayController::class, 'index'])
    ->defaults('page', 1)
    ->name('fatawa.day.today');
Route::get('/fatwa-today-{page}.htm', [FatwaDayController::class, 'index'])
    ->whereNumber('page')
    ->name('fatawa.day.today.paged');

// fatawa-friend-{id}.htm (.htaccess:307, op=friend — presumably a form
// display step) is NOT registered — no file among the 16 implements
// op=friend; same "no confirmed implementing code" category as above.
// Only fatawa-friend-sendemail-{id}.htm (op=sendemail, sendemail.php's
// confirmed implementation) is registered, as POST — sendemail.php reads
// $_POST exclusively, a GET would just show every validation error.
Route::post('/fatawa-friend-sendemail-{question}.htm', [FatwaQuestionController::class, 'sendToFriend'])
    ->whereNumber('question')
    ->name('fatawa.question.send-to-friend');

// advanced-search — Roadmap task 6.2. .htaccess:126,129 route both
// search.htm/advanced_search.htm to the same missing new_modules.php
// dispatcher. Only search.htm is registered — confirmed (Legacy Evidence
// Verification, task 6.2) to be the target of the active JS AJAX
// generation (new_advanced_search_mawad()/series()) and of header.php's
// own live, sitewide search form; advanced_search.htm is tied only to an
// older, dead AJAX generation (mode:"ajax_khotab", never matched by
// index.php's own checks) — out of scope, per explicit instruction. POST
// only, matching legacy exactly (see SearchController's own docblock for
// the full record of every decision this route embodies).
Route::post('/search.htm', [SearchController::class, 'search'])->name('search');
