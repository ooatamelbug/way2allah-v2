<?php

use App\Http\Middleware\MonitorsRequestPerformance;
use App\Support\Performance\PerformanceThresholds;
use App\Support\Performance\RequestMetrics;
use App\Support\Performance\SlowQueryListener;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Enhancement Batch E-01 — Performance Observability Foundation.
 *
 * Deliberately no `sleep()` anywhere: durations are asserted by feeding
 * the components fixed timestamps/durations directly (`RequestMetrics`
 * and `PerformanceThresholds` hold no clock of their own precisely so
 * this is possible), which keeps these tests exact rather than flaky.
 */

// ---- Threshold classification (pure, deterministic) ----

it('classifies a request faster than the warning threshold as not worth logging', function () {
    $thresholds = new PerformanceThresholds(warningMs: 400, slowMs: 1000, criticalMs: 2500);

    expect($thresholds->severityFor(0.0))->toBeNull()
        ->and($thresholds->severityFor(399.99))->toBeNull();
});

it('classifies warning, slow and critical requests at their exact boundaries', function () {
    $thresholds = new PerformanceThresholds(warningMs: 400, slowMs: 1000, criticalMs: 2500);

    expect($thresholds->severityFor(400.0))->toBe('warning')
        ->and($thresholds->severityFor(999.9))->toBe('warning')
        ->and($thresholds->severityFor(1000.0))->toBe('slow')
        ->and($thresholds->severityFor(2499.9))->toBe('slow')
        ->and($thresholds->severityFor(2500.0))->toBe('critical')
        ->and($thresholds->severityFor(9999.0))->toBe('critical');
});

it('maps each severity to a distinct log level so critical requests stand out', function () {
    $thresholds = new PerformanceThresholds(warningMs: 400, slowMs: 1000, criticalMs: 2500);

    expect($thresholds->logLevelFor('critical'))->toBe('error')
        ->and($thresholds->logLevelFor('slow'))->toBe('warning')
        ->and($thresholds->logLevelFor('warning'))->toBe('notice');
});

it('builds thresholds from the real config file', function () {
    $thresholds = PerformanceThresholds::fromConfig(config('performance'));

    expect($thresholds->warningMs)->toBe(400)
        ->and($thresholds->slowMs)->toBe(1000)
        ->and($thresholds->criticalMs)->toBe(2500);
});

// ---- Metric aggregation (pure, deterministic) ----

it('has a request id available immediately, without any request having run', function () {
    $metrics = new RequestMetrics;

    expect($metrics->requestId())->toMatch('/^[0-9a-f-]{36}$/');
});

it('aggregates query count, total duration and slowest query across connections', function () {
    $metrics = new RequestMetrics;

    $metrics->recordQuery(12.5);
    $metrics->recordQuery(300.25);
    $metrics->recordQuery(7.0);

    expect($metrics->queryCount())->toBe(3)
        ->and($metrics->totalQueryMs())->toBe(319.75)
        ->and($metrics->slowestQueryMs())->toBe(300.25);
});

it('computes duration from injected timestamps, and reports null when never started', function () {
    $metrics = new RequestMetrics;

    expect($metrics->durationMs(1000.0))->toBeNull();

    $metrics->start(1000.0);

    expect($metrics->durationMs(1002.5))->toBe(2500.0); // 2.5s → 2500ms
});

it('resets every counter and mints a new request id between requests', function () {
    $metrics = new RequestMetrics;
    $firstId = $metrics->requestId();

    $metrics->start(1000.0);
    $metrics->recordQuery(50.0);

    $metrics->reset();

    expect($metrics->requestId())->not->toBe($firstId)
        ->and($metrics->queryCount())->toBe(0)
        ->and($metrics->totalQueryMs())->toBe(0.0)
        ->and($metrics->slowestQueryMs())->toBe(0.0)
        ->and($metrics->startedAt())->toBeNull()
        ->and($metrics->durationMs(2000.0))->toBeNull();
});

// ---- Slow SQL listener ----

function makeQueryEvent(float $timeMs, string $sql = 'select * from `t` where `id` = ?', array $bindings = ['secret-value']): QueryExecuted
{
    $event = new QueryExecuted($sql, $bindings, $timeMs, DB::connection());
    $event->connectionName = 'main';

    return $event;
}

it('records every query into the metrics, regardless of whether it is slow', function () {
    $metrics = new RequestMetrics;
    $listener = new SlowQueryListener($metrics, slowQueryMs: 200, maxSqlLength: 500);

    $listener->handle(makeQueryEvent(5.0));
    $listener->handle(makeQueryEvent(250.0));

    expect($metrics->queryCount())->toBe(2)
        ->and($metrics->totalQueryMs())->toBe(255.0)
        ->and($metrics->slowestQueryMs())->toBe(250.0);
});

it('does not log a query below the slow-query threshold', function () {
    Log::spy();
    $listener = new SlowQueryListener(new RequestMetrics, slowQueryMs: 200, maxSqlLength: 500);

    $listener->handle(makeQueryEvent(199.9));

    Log::shouldNotHaveReceived('channel');
});

it('logs a query at or above the threshold with its duration, connection and request id', function () {
    $metrics = new RequestMetrics;
    $channel = Log::spy();
    Log::shouldReceive('channel')->with('slow-sql')->andReturn($channel);

    (new SlowQueryListener($metrics, slowQueryMs: 200, maxSqlLength: 500))
        ->handle(makeQueryEvent(250.0));

    $channel->shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($metrics) {
        return $message === 'Slow SQL query'
            && $context['request_id'] === $metrics->requestId()
            && $context['duration_ms'] === 250.0
            && $context['connection'] === 'main';
    })->once();
});

it('never writes query bindings into the slow-sql log — only the parameterised template', function () {
    $channel = Log::spy();
    Log::shouldReceive('channel')->with('slow-sql')->andReturn($channel);

    (new SlowQueryListener(new RequestMetrics, slowQueryMs: 200, maxSqlLength: 500))->handle(
        makeQueryEvent(
            300.0,
            'select * from `users` where `email` = ? and `password` = ?',
            ['victim@example.com', 'hunter2'],
        )
    );

    $channel->shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        return ! str_contains($context['sql'], 'victim@example.com')
            && ! str_contains($context['sql'], 'hunter2')
            && ! array_key_exists('bindings', $context)
            && str_contains($context['sql'], '?');
    })->once();
});

it('truncates a pathologically long SQL template instead of writing it whole', function () {
    $channel = Log::spy();
    Log::shouldReceive('channel')->with('slow-sql')->andReturn($channel);

    (new SlowQueryListener(new RequestMetrics, slowQueryMs: 200, maxSqlLength: 50))
        ->handle(makeQueryEvent(300.0, 'select '.str_repeat('`col`, ', 200).' from `t`'));

    $channel->shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => mb_strlen($context['sql']) <= 51
    )->once();
});

it('swallows a logging failure rather than letting it escape into the request', function () {
    Log::shouldReceive('channel')->andThrow(new RuntimeException('log target unavailable'));
    $metrics = new RequestMetrics;

    (new SlowQueryListener($metrics, slowQueryMs: 200, maxSqlLength: 500))
        ->handle(makeQueryEvent(300.0));

    // The query is still counted, and no exception reached the caller.
    expect($metrics->queryCount())->toBe(1);
});

// ---- Middleware behaviour through a real request ----

it('writes no performance log for a request faster than the warning threshold', function () {
    config(['performance.enabled' => true, 'performance.warning_ms' => 60_000]);
    Log::spy();

    $this->get('/privacy')->assertOk();

    Log::shouldNotHaveReceived('channel', ['performance']);
});

it('writes a performance log entry, with the full metric set, once a request exceeds the threshold', function () {
    // Threshold of 0ms makes every request qualify — no sleep needed.
    config(['performance.enabled' => true, 'performance.warning_ms' => 0, 'performance.slow_ms' => 999_999, 'performance.critical_ms' => 999_999]);

    $channel = Log::spy();
    Log::shouldReceive('channel')->with('performance')->andReturn($channel);

    $this->get('/privacy')->assertOk();

    $channel->shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) {
        return $level === 'notice'
            && $message === 'Slow request'
            && $context['severity'] === 'warning'
            && $context['route_uri'] === 'privacy'
            && $context['method'] === 'GET'
            && $context['status_code'] === 200
            && is_float($context['duration_ms'])
            && is_int($context['query_count'])
            && is_float($context['total_query_ms'])
            && is_float($context['slowest_query_ms'])
            && is_int($context['peak_memory_bytes'])
            && preg_match('/^[0-9a-f-]{36}$/', $context['request_id']) === 1;
    })->once();
});

it('writes nothing at all when monitoring is disabled', function () {
    config(['performance.enabled' => false, 'performance.warning_ms' => 0]);
    Log::spy();

    $this->get('/privacy')->assertOk();

    Log::shouldNotHaveReceived('channel');
});

it('skips a route listed in the exclusion config', function () {
    config([
        'performance.enabled' => true,
        'performance.warning_ms' => 0,
        'performance.excluded_routes' => ['privacy'],
    ]);
    Log::spy();

    $this->get('/privacy')->assertOk();

    Log::shouldNotHaveReceived('channel', ['performance']);
});

it('never lets a failure inside performance logging turn a working page into a 500', function () {
    config(['performance.enabled' => true, 'performance.warning_ms' => 0]);
    Log::shouldReceive('channel')->andThrow(new RuntimeException('log target unavailable'));

    $response = $this->get('/privacy');

    $response->assertOk();
});

it('reuses one request id across the request log and its own slow queries', function () {
    $metrics = app(RequestMetrics::class);
    $metrics->reset();
    $idAtStart = $metrics->requestId();

    $channel = Log::spy();
    Log::shouldReceive('channel')->with('slow-sql')->andReturn($channel);

    (new SlowQueryListener($metrics, slowQueryMs: 200, maxSqlLength: 500))
        ->handle(makeQueryEvent(300.0));

    $channel->shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $context['request_id'] === $idAtStart
    )->once();
});

it('resolves RequestMetrics as a scoped instance, so it is shared within a request', function () {
    expect(app(RequestMetrics::class))->toBe(app(RequestMetrics::class));
});

it('registers the monitoring middleware globally', function () {
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    expect($kernel->hasMiddleware(MonitorsRequestPerformance::class))->toBeTrue();
});
