<?php

use App\Domain\Content\Http\Controllers\AnasheedGroupController;
use App\Domain\Content\Http\Controllers\AnasheedItemController;
use App\Domain\Content\Http\Controllers\AnasheedNewsController;
use App\Domain\Content\Http\Controllers\CategoryController;
use App\Domain\Content\Http\Controllers\ChannelController;
use App\Domain\Content\Http\Controllers\ChatRoomLessonController;
use App\Domain\Content\Http\Controllers\GalleryController;
use App\Domain\Content\Http\Controllers\KhotabAuthorController;
use App\Domain\Content\Http\Controllers\KhotabDayController;
use App\Domain\Content\Http\Controllers\KhotabDumpController;
use App\Domain\Content\Http\Controllers\KhotabGroupController;
use App\Domain\Content\Http\Controllers\KhotabItemController;
use App\Domain\Content\Http\Controllers\KhotabNewsController;
use App\Domain\Content\Http\Controllers\KhotabSearchController;
use App\Domain\Content\Http\Controllers\KhotabSeriesController;
use App\Domain\Content\Http\Controllers\LiveStreamController;
use App\Domain\Content\Http\Controllers\RadioController;
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
| khotab-{video|audio|pdf}.htm / khotab-{video|audio|pdf}-{author}.htm /
| khotab-group-{id}.htm / khotab-series-{id}.htm / khotab-{video|audio}-today.htm /
| khotab-{video|audio}date-{d}-{m}-{y}.htm / khotab-{video|audio|pdf}_news.htm
| are all real, live .htaccess rules — kept at their exact legacy path.
| khotab/dump.php has NO .htaccess rule at all (confirmed) — same
| raw-path-only profile as live-stream/live.php (Wave 3): new path here,
| legacy-path redirect in config/legacy-url-map.php.
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

// khotab/search.php has no .htaccess rule at all (confirmed, IF-018's
// evidence) — same raw-path-only profile as khotab/dump.php above.
Route::get('/khotab/search', [KhotabSearchController::class, 'index'])->name('khotab.search');

// category-{id}.htm is a real, live .htaccess rule (categories/category.php,
// Roadmap task 4.3). categories.htm (categories/tree.php, the category
// index/tree) and category-series-{id}-{id2}.htm (categories/series.php)
// are NOT yet ported — deferred, not silently dropped (CategoryController's
// own docblock).
Route::get('/category-{category}.htm', [CategoryController::class, 'show'])
    ->whereNumber('category')
    ->name('categories.show');

// vars_categories/ note (task 4.2 amendment, added post-Wave-4 — see
// docs/reviews/gap-closure-action-plan.md item 1): confirmed, by direct
// diff, to be an older, superseded duplicate of categories/ — same
// tables, same category-id space, no distinct content. Its 3 live
// .htaccess routes close as redirects to their categories/ equivalents,
// not a new controller. Only this one is buildable right now — the other
// 2 (vars-categories.htm -> categories.htm, vars-category-series-{id}-
// {id2}.htm -> category-series-{id}-{id2}.htm) redirect to Laravel routes
// that don't exist yet (both still deferred, per the comment above) and
// are intentionally NOT added here to avoid redirecting to a dead route —
// see IF-031.
Route::redirect('/vars-category-{category}.htm', '/category-{category}.htm')
    ->whereNumber('category')
    ->name('categories.vars-redirect');

// w2acd — Roadmap task 4.5. IF-026: none of this module's pretty
// cds-*.htm URLs actually reach these files (they all route to a
// nonexistent new_modules.php) — registered at the exact raw legacy
// path instead, since Route::redirect() can't forward the query-string
// ids (?id=, ?khid=) these pages' identity actually lives in.
Route::get('/w2acd/cds.php', [W2acdController::class, 'index'])->name('w2acd.index');
Route::get('/w2acd/item.php', [W2acdController::class, 'show'])->name('w2acd.show');

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
Route::get('/var-download-{anasheed}.htm', [AnasheedItemController::class, 'download'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.download');
Route::get('/var-mirror-{anasheed}-{mirror}.htm', [AnasheedItemController::class, 'downloadMirror'])
    ->whereNumber(['anasheed', 'mirror'])
    ->name('anasheed.item.download-mirror');
Route::post('/var-item-{anasheed}/comments', [AnasheedItemController::class, 'storeComment'])
    ->whereNumber('anasheed')
    ->name('anasheed.item.store-comment');
Route::get('/var-group-{group}.htm', [AnasheedGroupController::class, 'show'])
    ->whereNumber('group')
    ->name('anasheed.group.show');

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
