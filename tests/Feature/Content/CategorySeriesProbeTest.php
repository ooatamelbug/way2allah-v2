<?php

use App\Domain\Content\Services\ContentSidebarWidget;
use App\Support\Performance\RequestMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * TEMPORARY probe (P-1) — delete this file together with
 * `ContentSidebarWidget::rememberRowsProbed()`, the
 * `category-series-probe` log channel and the
 * `performance.category_series_probe` config key.
 *
 * The probe exists to recover the one fact production logs cannot supply:
 * which `category_id` values actually execute the two `categories.series`
 * top-items queries on cache fill, and how long those fills take.
 * `SlowQueryListener` never reads bindings and `MonitorsRequestPerformance`
 * logs only the route template, so the category id appears nowhere.
 *
 * Records are emitted from INSIDE the `Cache::remember()` closure, which
 * runs if and only if the entry is absent — so "miss only" is structural,
 * not conditional. These tests pin that, the four permitted fields, and
 * the fact that nothing about the cache contract moved.
 */
function useInMemoryMainConnectionForCategorySeriesProbe(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

/**
 * Rebinds the probe channel to an in-memory Monolog handler, so the real
 * `Log::channel()` path is exercised but nothing reaches `storage/logs`.
 */
function probeHandler(): TestHandler
{
    config(['logging.channels.category-series-probe' => [
        'driver' => 'monolog',
        'handler' => TestHandler::class,
    ]]);

    Log::forgetChannel('category-series-probe');

    /** @var TestHandler $handler */
    $handler = Log::channel('category-series-probe')->getLogger()->getHandlers()[0];

    return $handler;
}

/** @return int number of queries the callback issued on the `main` connection */
function countProbeMainQueries(Closure $callback): int
{
    $count = 0;
    DB::connection('main')->listen(function () use (&$count) {
        $count++;
    });

    $callback();

    return $count;
}

function seedProbeFixture(): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 7, 'title' => 'Video A', 'vedio' => 1, 'hidden' => 0, 'hits' => 500, 'time' => 100, 'frame' => 0, 'pdf' => 0, 'channel_id' => 3],
        ['id' => 2, 'author' => 7, 'title' => 'Video B', 'vedio' => 1, 'hidden' => 0, 'hits' => 900, 'time' => 200, 'frame' => 0, 'pdf' => 0, 'channel_id' => 3],
        ['id' => 3, 'author' => 8, 'title' => 'Audio C', 'vedio' => 0, 'hidden' => 0, 'hits' => 700, 'time' => 300, 'frame' => 0, 'pdf' => 4, 'channel_id' => 9],
    ]);

    DB::connection('main')->table('khotab_category_index')->insert([
        ['khotab_id' => 1, 'category_id' => 11],
        ['khotab_id' => 2, 'category_id' => 11],
        ['khotab_id' => 3, 'category_id' => 11],   // vedio=0, filtered out
        ['khotab_id' => 1, 'category_id' => 12],
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategorySeriesProbe();
    Cache::flush();
    seedProbeFixture();
    config(['performance.category_series_probe' => true]);
});

// ---- miss-only emission -------------------------------------------------

it('a cold call emits exactly one probe record and runs exactly one database query', function () {
    $handler = probeHandler();
    $widget = app(ContentSidebarWidget::class);

    $queries = countProbeMainQueries(fn () => $widget->khotabMostDownloadedByCategoryForSeries(11));

    expect($queries)->toBe(1)
        ->and($handler->getRecords())->toHaveCount(1);
});

it('a warm call emits no probe record and runs no database query', function () {
    $handler = probeHandler();
    $widget = app(ContentSidebarWidget::class);

    $widget->khotabMostDownloadedByCategoryForSeries(11);
    $handler->clear();

    $queries = countProbeMainQueries(fn () => $widget->khotabMostDownloadedByCategoryForSeries(11));

    expect($queries)->toBe(0)
        ->and($handler->getRecords())->toBe([]);
});

it('both orderings emit their own miss record with the correct order value', function () {
    $handler = probeHandler();
    $widget = app(ContentSidebarWidget::class);

    $widget->khotabMostDownloadedByCategoryForSeries(11);
    $widget->khotabMostRecentByCategoryForSeries(11);

    $orders = array_map(fn ($r) => $r['context']['order'], $handler->getRecords());

    expect($handler->getRecords())->toHaveCount(2)
        ->and($orders)->toBe(['hits', 'time']);
});

// ---- privacy guard -----------------------------------------------------

it('records exactly the four permitted fields and nothing else', function () {
    $handler = probeHandler();

    app(ContentSidebarWidget::class)->khotabMostDownloadedByCategoryForSeries(11);

    $context = $handler->getRecords()[0]['context'];

    // Exact key set, not a subset — a future extra field must fail here.
    expect(array_keys($context))->toBe(['request_id', 'category_id', 'order', 'duration_ms']);
});

it('records correct, correctly-typed values and the current request correlation id', function () {
    $handler = probeHandler();

    app(ContentSidebarWidget::class)->khotabMostRecentByCategoryForSeries(12);

    $context = $handler->getRecords()[0]['context'];

    expect($context['category_id'])->toBe(12)
        ->and($context['order'])->toBe('time')
        ->and($context['duration_ms'])->toBeFloat()
        ->and($context['duration_ms'])->toBeGreaterThanOrEqual(0.0)
        ->and($context['request_id'])->toBe(app(RequestMetrics::class)->requestId());
});

// ---- the cache contract did not move -----------------------------------

it('still calls Cache::remember exactly once with the unchanged key and the unchanged 300s TTL', function () {
    probeHandler();

    Cache::partialMock()
        ->shouldReceive('remember')
        ->once()
        ->with(
            'sidebar:category-series-khotab:category=11:limit=5:order=hits',
            300,
            Mockery::type(Closure::class)
        )
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

    app(ContentSidebarWidget::class)->khotabMostDownloadedByCategoryForSeries(11);
});

it('still calls Cache::remember exactly once with the unchanged key and TTL for the time ordering', function () {
    probeHandler();

    Cache::partialMock()
        ->shouldReceive('remember')
        ->once()
        ->with(
            'sidebar:category-series-khotab:category=11:limit=5:order=time',
            300,
            Mockery::type(Closure::class)
        )
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

    app(ContentSidebarWidget::class)->khotabMostRecentByCategoryForSeries(11);
});

it('returns byte-identical rows with the probe off and on, including the thumbnail decoration', function () {
    probeHandler();
    $widget = app(ContentSidebarWidget::class);

    config(['performance.category_series_probe' => false]);
    $off = $widget->khotabMostDownloadedByCategoryForSeries(11);

    Cache::flush();

    config(['performance.category_series_probe' => true]);
    $on = $widget->khotabMostDownloadedByCategoryForSeries(11);

    expect($on->map(fn ($row) => (array) $row)->all())
        ->toBe($off->map(fn ($row) => (array) $row)->all())
        ->and($on->pluck('thumb')->all())->toBe($off->pluck('thumb')->all());
});

it('treats an empty cached result as a hit — one miss on the first call, nothing on the second', function () {
    $handler = probeHandler();
    $widget = app(ContentSidebarWidget::class);

    // Category 999 maps no rows, so the closure caches [] — which is not
    // null, so Cache::remember serves it as a hit next time.
    expect($widget->khotabMostRecentByCategoryForSeries(999)->all())->toBe([])
        ->and($handler->getRecords())->toHaveCount(1);

    $handler->clear();

    expect($widget->khotabMostRecentByCategoryForSeries(999)->all())->toBe([])
        ->and($handler->getRecords())->toBe([]);
});

// ---- failure containment and scope -------------------------------------

it('a probe logging failure cannot break the page', function () {
    Log::shouldReceive('channel')
        ->with('category-series-probe')
        ->andThrow(new RuntimeException('probe channel unavailable'));

    $rows = app(ContentSidebarWidget::class)->khotabMostDownloadedByCategoryForSeries(11);

    // hits DESC: id 2 (900) then id 1 (500). id 3 excluded — vedio=0.
    expect($rows->pluck('id')->all())->toBe([2, 1]);
});

it('emits nothing at all while the flag is off, but still runs the query', function () {
    $handler = probeHandler();
    config(['performance.category_series_probe' => false]);

    $queries = countProbeMainQueries(
        fn () => app(ContentSidebarWidget::class)->khotabMostDownloadedByCategoryForSeries(11)
    );

    expect($queries)->toBe(1)
        ->and($handler->getRecords())->toBe([]);
});

it('instruments no other widget — the categories.show pair, the channel pair and topitems stay silent', function () {
    $handler = probeHandler();
    $widget = app(ContentSidebarWidget::class);

    // C-2's categories.show pair — the near-identical widget that also
    // filters hidden=0. Must not emit.
    $widget->khotabMostDownloadedByCategory(11);
    $widget->khotabMostRecentByCategory(11);

    // E-02's channel pair, and a plain topitems()-backed widget.
    $widget->channelMostDownloadedKhotabItems(3);
    $widget->channelMostRecentKhotabItems(3);
    $widget->khotabMostDownloadedByVideoFlag(true);

    expect($handler->getRecords())->toBe([]);
});
