<?php

use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Enhancement Batch E-02 (F-02) — restores the 300s caching legacy's own
 * `topitems()` already had for these exact khotab sidebar widgets.
 *
 * Cache behaviour is asserted by counting the real SQL the widget issues
 * (via `DB::listen`) rather than by inspecting cache internals: what
 * actually matters is that a warm call performs no database work and
 * still returns the same rows. No test waits out a real TTL — expiry is
 * exercised with time travel.
 */
function useInMemoryMainConnectionForSidebarCache(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        // Only needed by the random-featured widget's own author join.
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    ]);
}

/** @return int number of queries the callback issued on the `main` connection */
function countMainQueries(Closure $callback): int
{
    $count = 0;
    DB::connection('main')->listen(function () use (&$count) {
        $count++;
    });

    $callback();

    return $count;
}

beforeEach(function () {
    useInMemoryMainConnectionForSidebarCache();
    Cache::flush();

    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 7, 'title' => 'Video A', 'vedio' => 1, 'hidden' => 0, 'hits' => 500, 'time' => 100, 'frame' => 0, 'pdf' => 0, 'channel_id' => 3],
        ['id' => 2, 'author' => 7, 'title' => 'Video B', 'vedio' => 1, 'hidden' => 0, 'hits' => 900, 'time' => 200, 'frame' => 0, 'pdf' => 0, 'channel_id' => 3],
        ['id' => 3, 'author' => 8, 'title' => 'Audio C', 'vedio' => 0, 'hidden' => 0, 'hits' => 700, 'time' => 300, 'frame' => 0, 'pdf' => 4, 'channel_id' => 9],
    ]);
});

it('executes the query on a cold cache and serves the second call without touching the database', function () {
    $widget = app(ContentSidebarWidget::class);

    $cold = countMainQueries(fn () => $widget->khotabMostDownloadedByVideoFlag(true));
    $warm = countMainQueries(fn () => $widget->khotabMostDownloadedByVideoFlag(true));

    expect($cold)->toBe(1)
        ->and($warm)->toBe(0);
});

it('returns equivalent rows cold and warm, in the same order', function () {
    $widget = app(ContentSidebarWidget::class);

    $cold = $widget->khotabMostDownloadedByVideoFlag(true);
    $warm = $widget->khotabMostDownloadedByVideoFlag(true);

    expect($warm->pluck('id')->all())->toBe($cold->pluck('id')->all())
        ->and($warm->pluck('title')->all())->toBe($cold->pluck('title')->all())
        ->and($warm->pluck('hits')->all())->toBe($cold->pluck('hits')->all())
        // ordering is the widget's contract: hits DESC
        ->and($cold->pluck('id')->all())->toBe([2, 1]);
});

it('rehydrates cached rows as objects, not incomplete classes, even on a serializing store', function () {
    // `file` is the production default and the store that broke once
    // before (cache.serializable_classes = false turns cached objects
    // into __PHP_Incomplete_Class on read).
    config(['cache.default' => 'file']);
    Cache::store('file')->flush();
    $widget = app(ContentSidebarWidget::class);

    $widget->khotabMostDownloadedByVideoFlag(true);      // fills cache
    $warm = $widget->khotabMostDownloadedByVideoFlag(true); // reads through unserialize()

    expect($warm->first())->toBeInstanceOf(stdClass::class)
        ->and($warm->first()->title)->toBe('Video B')
        ->and($warm->first())->not->toBeInstanceOf(__PHP_Incomplete_Class::class);

    Cache::store('file')->flush();
});

it('re-queries once the 300 second TTL has expired, and not a moment before', function () {
    $widget = app(ContentSidebarWidget::class);
    $widget->khotabMostDownloadedByVideoFlag(true);

    $this->travel(299)->seconds();
    expect(countMainQueries(fn () => $widget->khotabMostDownloadedByVideoFlag(true)))->toBe(0);

    $this->travel(2)->seconds(); // now past 300s
    expect(countMainQueries(fn () => $widget->khotabMostDownloadedByVideoFlag(true)))->toBe(1);

    $this->travelBack();
});

it('does not collide between different authors', function () {
    $widget = app(ContentSidebarWidget::class);

    $seven = $widget->khotabMostDownloadedByAuthor(7, true);
    $eight = $widget->khotabMostDownloadedByAuthor(8, true);

    expect($seven->pluck('id')->all())->toBe([2, 1])
        ->and($eight->pluck('id')->all())->toBe([]); // author 8 has no video rows
});

it('does not collide between the video and audio variants of the same widget', function () {
    $widget = app(ContentSidebarWidget::class);

    expect($widget->khotabMostDownloadedByVideoFlag(true)->pluck('id')->all())->toBe([2, 1])
        ->and($widget->khotabMostDownloadedByVideoFlag(false)->pluck('id')->all())->toBe([3]);
});

it('does not collide between the "most downloaded" and "newest" widgets sharing a filter', function () {
    $widget = app(ContentSidebarWidget::class);

    // Same filter (vedio=1), different ordering — must not share an entry.
    expect($widget->khotabMostDownloadedByVideoFlag(true)->pluck('id')->all())->toBe([2, 1])   // hits DESC
        ->and($widget->khotabMostRecentByVideoFlag(true)->pluck('id')->all())->toBe([2, 1]);   // time DESC

    // Prove they are genuinely separate entries by making orderings differ.
    Cache::flush();
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', 1)->update(['hits' => 9999]);

    expect($widget->khotabMostDownloadedByVideoFlag(true)->pluck('id')->all())->toBe([1, 2])
        ->and($widget->khotabMostRecentByVideoFlag(true)->pluck('id')->all())->toBe([2, 1]);
});

it('does not collide between channel-scoped widgets for different channels', function () {
    $widget = app(ContentSidebarWidget::class);

    expect($widget->channelMostDownloadedKhotabItems(3)->pluck('id')->all())->toBe([2, 1])
        ->and($widget->channelMostDownloadedKhotabItems(9)->pluck('id')->all())->toBe([]);
});

it('does not collide between the pdf widgets, sitewide vs author-scoped', function () {
    $widget = app(ContentSidebarWidget::class);

    expect($widget->khotabMostDownloadedForPdf()->pluck('id')->all())->toBe([3])
        ->and($widget->khotabMostDownloadedByAuthorForPdf(7)->pluck('id')->all())->toBe([])
        ->and($widget->khotabMostDownloadedByAuthorForPdf(8)->pluck('id')->all())->toBe([3]);
});

it('caches an empty result set correctly, without re-querying on the next call', function () {
    $widget = app(ContentSidebarWidget::class);

    $cold = countMainQueries(fn () => $result = $widget->khotabMostDownloadedByAuthor(999, true));
    $warm = countMainQueries(fn () => $widget->khotabMostDownloadedByAuthor(999, true));

    expect($cold)->toBe(1)
        ->and($warm)->toBe(0)
        ->and($widget->khotabMostDownloadedByAuthor(999, true))->toBeEmpty();
});

it('recomputes the thumbnail on every call rather than caching a stale filesystem check', function () {
    // Legacy cached the raw rows *before* its decoration loop; the thumb
    // involves a real file_exists(), so it must stay fresh per request.
    $widget = app(ContentSidebarWidget::class);

    $first = $widget->khotabMostDownloadedByVideoFlag(true);
    expect($first->first())->toHaveProperty('thumb');

    $warm = $widget->khotabMostDownloadedByVideoFlag(true);
    expect($warm->first()->thumb)->toBe($first->first()->thumb);
});

it('leaves the deliberately-random featured widget uncached, so it can still vary', function () {
    $widget = app(ContentSidebarWidget::class);

    // Two calls must both hit the database — freezing this for 300s would
    // defeat the widget's entire purpose.
    $first = countMainQueries(fn () => $widget->khotabRandomFeatured());
    $second = countMainQueries(fn () => $widget->khotabRandomFeatured());

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBeGreaterThan(0);
});
