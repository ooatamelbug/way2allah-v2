<?php

/*
 * Phase 2 — idempotent aggregation, in one process (SQLite stand-in for `main`; the migration is skipped under tests
 * and the tables come from TelemetrySchema itself). Crash and concurrency cases across real processes are in
 * RollupCrashTest.
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\HyperLogLog;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\InMemoryConnection;

const RLP_MINUTE = 29_379_840;              // 2025-11-10 16:00:00 UTC (hour-aligned)
const RLP_HOUR = RLP_MINUTE * 60;
const RLP_FINALIZE_AT = RLP_HOUR + 3600 + 900 + 5;   // after the hour's end + grace

function rlpSpool(object $test, array $o = []): SpoolConfig
{
    return SpoolConfig::fromArray($o + ['spool' => 'on', 'root' => $test->root, 'ledger_shards' => 2, 'ledger_slots' => 10080,
        'disk_reserve_bytes' => 0, 'seal_grace_minutes' => 0]);
}

function rlpConfig(array $o = []): RollupConfig
{
    return RollupConfig::fromArray($o + ['aggregate' => 'on', 'connection' => 'main', 'done_retention_hours' => 24]);
}

function rlpRun(object $test, int $now, array $config = [], ?Closure $hook = null, array $spool = []): array
{
    return (new RollupRunner(rlpSpool($test, $spool), rlpConfig($config), $hook, fn (): float => (float) $now))->run();
}

/** Write events through the real spool writer, as if at the given minute. */
function rlpWrite(object $test, int $minute, array $events, array $spool = []): void
{
    $writer = new SpoolWriter(rlpSpool($test, $spool), fn (): float => $minute * 60 + 1.0);
    foreach ($events as $event) {
        expect($writer->write($event + ['ev' => 'capacity_rejected', 'mod' => 'khotab', 't' => $minute * 60 + 1.0]))->toBe(SpoolWriter::WRITTEN);
    }
}

/** Coverage evidence for minutes [from, to] — what the per-minute scheduled rollup records. */
function rlpCover(int $from, int $to): void
{
    $rows = [];
    for ($m = $from; $m <= $to; $m++) {
        $rows[] = ['minute' => $m, 'spool_enabled' => true, 'spool_writable' => true, 'breaker_open' => false, 'spool_full' => false, 'recorded_at' => $m * 60];
    }
    foreach (array_chunk($rows, 300) as $chunk) {
        DB::connection('main')->table(T::COVERAGE)->insertOrIgnore($chunk);
    }
}

function rlpPeriod(string $granularity, int $start): ?object
{
    return DB::connection('main')->table(T::PERIODS)->where('granularity', $granularity)->where('period_start', $start)->first();
}

function rlpEvents(): int
{
    return (int) DB::connection('main')->table(T::METRICS_5M)->sum('events');
}

function rlpEventsN(int $n, array $extra = []): array
{
    return array_map(fn ($i) => ['id' => "e{$i}", 'ip' => '203.0.113.'.($i % 5), 'uah' => 'ua'.($i % 2), 'rid' => $i] + $extra, range(1, $n));
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-rollup-'.bin2hex(random_bytes(5));
    InMemoryConnection::setup('main', T::tables());
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    if (is_dir($this->root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
            @chmod($f->getPathname(), 0775);
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }
});

// --- Exactly once -------------------------------------------------------------------------------------------------

it('applies a sealed batch exactly once: ledger row, aggregates and the move to done/ — a second run changes nothing', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(100));

    $first = rlpRun($this, RLP_HOUR + 180);
    $second = rlpRun($this, RLP_HOUR + 240);

    expect($first)->toMatchArray(['sealed' => 1, 'applied' => 1, 'events' => 100, 'errors' => []])
        ->and($second)->toMatchArray(['sealed' => 0, 'applied' => 0, 'errors' => []])
        ->and(DB::connection('main')->table(T::BATCHES)->count())->toBe(1)
        ->and(rlpEvents())->toBe(100)
        ->and((int) DB::connection('main')->table(T::METRICS_DAILY)->sum('events'))->toBe(100)
        ->and(glob($this->root.'/done/*.ndjson'))->toHaveCount(1)
        ->and(glob($this->root.'/sealed/*.ndjson'))->toBe([]);
});

it('duplicate submission: the same batch again, or its content under another id, is never applied twice; a conflict is quarantined', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(10));
    rlpRun($this, RLP_HOUR + 180);
    $done = glob($this->root.'/done/*.ndjson')[0];
    $id = basename($done, '.ndjson');

    copy($done, $this->root.'/sealed/'.$id.'.ndjson');                                 // replayed as-is
    copy($done, $this->root.'/sealed/202511101600-1-deadbeef.ndjson');                 // same content, new id
    file_put_contents($this->root.'/sealed/'.$id.'.ndjson.tmp', file_get_contents($done)."\n");
    $report = rlpRun($this, RLP_HOUR + 240);
    rename($this->root.'/sealed/'.$id.'.ndjson.tmp', $this->root.'/sealed/'.$id.'.ndjson'); // same id, different content
    $conflict = rlpRun($this, RLP_HOUR + 300);

    expect($report)->toMatchArray(['applied' => 0, 'already_applied' => 1, 'duplicate_content' => 1])
        ->and($conflict)->toMatchArray(['applied' => 0, 'conflicts' => 1])
        ->and(rlpEvents())->toBe(10)
        ->and(DB::connection('main')->table(T::BATCHES)->count())->toBe(1)
        ->and(glob($this->root.'/quarantine/*.conflict-*.ndjson'))->toHaveCount(1);

    expect(Artisan::call('downloads:telemetry-replay', ['batch' => $id]))->toBe(1);   // replay refused: already in the ledger
});

it('rolls back everything when the transaction fails, leaving the batch to be applied once later', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(20));

    $failed = rlpRun($this, RLP_HOUR + 180, [], function (string $point) {
        if ($point === 'before_commit') {
            throw new RuntimeException('simulated database failure');
        }
    });

    expect($failed['errors'])->not->toBe([])
        ->and(DB::connection('main')->table(T::BATCHES)->count())->toBe(0)
        ->and(rlpEvents())->toBe(0)
        ->and(DB::connection('main')->table(T::IP_UA_HOURLY)->count())->toBe(0)
        ->and(DB::connection('main')->table(T::SKETCHES)->count())->toBe(0)
        ->and(DB::connection('main')->table(T::PERIODS)->count())->toBe(0)
        ->and(glob($this->root.'/sealed/*.ndjson'))->toHaveCount(1);

    expect(rlpRun($this, RLP_HOUR + 240)['applied'])->toBe(1)->and(rlpEvents())->toBe(20);
});

// --- Definitions ----------------------------------------------------------------------------------------------------

it('counts distinct IP addresses and IP/User-Agent combinations exactly per hour — and they are different things', function () {
    rlpWrite($this, RLP_MINUTE, [
        ['ip' => '198.51.100.1', 'uah' => 'chrome', 'rid' => 1],
        ['ip' => '198.51.100.1', 'uah' => 'firefox', 'rid' => 2],    // same IP, second User-Agent
        ['ip' => '198.51.100.1', 'uah' => 'chrome', 'rid' => 1],
        ['ip' => '198.51.100.2', 'uah' => 'chrome', 'rid' => 3],
        ['ip' => '198.51.100.3', 'rid' => 4],                         // no User-Agent hash
        ['rid' => 5],                                                  // no IP: counted in metrics, not per client
    ]);
    rlpRun($this, RLP_HOUR + 180);
    $q = DB::connection('main')->table(T::IP_UA_HOURLY)->where('hour', RLP_HOUR);

    expect((clone $q)->distinct()->count('ip'))->toBe(3)                 // distinct IP addresses
        ->and((clone $q)->count())->toBe(4)                               // IP/User-Agent combinations (not people)
        ->and((int) (clone $q)->sum('events'))->toBe(5)
        ->and(json_decode((string) (clone $q)->where('ip', '198.51.100.1')->where('ua_hash', 'chrome')->value('resources')))->toBe(['khotab:1::'])
        ->and(rlpEvents())->toBe(6);
});

it('keeps an exact resource set per IP/User-Agent combination up to 64, then flags overflow', function () {
    rlpWrite($this, RLP_MINUTE, array_map(fn ($i) => ['ip' => '192.0.2.9', 'uah' => 'bot', 'rid' => $i], range(1, 70)));
    rlpRun($this, RLP_HOUR + 180);
    $row = DB::connection('main')->table(T::IP_UA_HOURLY)->first();

    expect(count(json_decode((string) $row->resources)))->toBe(64)->and((bool) $row->resources_overflow)->toBeTrue();
});

it('keeps daily distinct estimates as mergeable HyperLogLog sketches, approximately right across batches', function () {
    foreach ([0, 1, 2] as $offset) {   // three batches, overlapping IP sets
        rlpWrite($this, RLP_MINUTE + $offset, array_map(fn ($i) => ['ip' => '10.0.'.intdiv($i, 250).'.'.($i % 250), 'rid' => $i], range($offset * 500, $offset * 500 + 999)));
    }
    rlpRun($this, RLP_HOUR + 300);
    $registers = (string) DB::connection('main')->table(T::SKETCHES)->where('dimension', 'ip')->value('registers');

    expect(abs((new HyperLogLog($registers))->estimate() - 2000) / 2000)->toBeLessThan(0.05);   // 2,000 distinct, 3,000 events
});

// --- Period quality -------------------------------------------------------------------------------------------------

it('finalizes an hour only with full coverage evidence and no known gaps', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(10));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);

    expect(rlpRun($this, RLP_HOUR + 1800)['applied'])->toBe(1)
        ->and(rlpPeriod('hour', RLP_HOUR)->state)->toBe('provisional');           // too early: the hour has not ended

    rlpRun($this, RLP_FINALIZE_AT);
    $period = rlpPeriod('hour', RLP_HOUR);
    expect($period->state)->toBe('final')->and((int) $period->coverage_minutes)->toBe(60)
        ->and(json_decode($period->reasons))->toBe([])->and((int) $period->events)->toBe(10);
});

it('does not finalize an hour just because every visible batch was processed: missing coverage makes it incomplete', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(10));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 49);                                         // 10 minutes without evidence

    rlpRun($this, RLP_FINALIZE_AT);
    $period = rlpPeriod('hour', RLP_HOUR);

    expect($period->state)->toBe('incomplete')->and((int) $period->coverage_minutes)->toBe(50)
        ->and(json_decode($period->reasons))->toContain('coverage_gap');
});

it('a disabled spool during the hour is reported as such', function () {
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);
    DB::connection('main')->table(T::COVERAGE)->where('minute', RLP_MINUTE + 5)->update(['spool_enabled' => false]);

    rlpRun($this, RLP_FINALIZE_AT);

    expect(json_decode(rlpPeriod('hour', RLP_HOUR)->reasons))->toContain('spool_disabled');
});

it('recorded drops and ledger uncertainty prevent "final"', function (string $kind, string $reason) {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(5));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);
    $spool = rlpSpool($this);
    $ledger = new DropLedger($spool);
    if ($kind === 'drops') {
        $ledger->record(RLP_MINUTE + 7, ['dropped_events' => 3, 'reason' => 2]);
    } else {
        $ledger->record(RLP_MINUTE + 7, ['dropped_events' => 1, 'reason' => 2]);
        foreach (glob($this->root.'/drops/*.bin') as $shard) {                     // tear the slot in whichever shard holds it
            $h = fopen($shard, 'r+b');
            fseek($h, DropLedger::HEADER_BYTES + ((RLP_MINUTE + 7) % 10080) * DropLedger::SLOT_BYTES + 8);
            fwrite($h, "\xFF");
            fclose($h);
        }
    }

    rlpRun($this, RLP_FINALIZE_AT);
    $period = rlpPeriod('hour', RLP_HOUR);

    expect($period->state)->toBe('incomplete')->and(json_decode($period->reasons))->toContain($reason);
})->with([
    'recorded drops' => ['drops', 'dropped_events'],
    'torn ledger slot' => ['torn', 'ledger_uncertain'],
]);

it('drop evidence that appears after finalization turns a final hour incomplete (never silently kept final)', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(5));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);
    rlpRun($this, RLP_FINALIZE_AT);
    expect(rlpPeriod('hour', RLP_HOUR)->state)->toBe('final');

    (new DropLedger(rlpSpool($this)))->record(RLP_MINUTE + 30, ['dropped_events' => 2, 'reason' => 2]);   // e.g. a shutdown flush
    rlpRun($this, RLP_FINALIZE_AT + 600);
    $period = rlpPeriod('hour', RLP_HOUR);

    expect($period->state)->toBe('incomplete')->and(json_decode($period->reasons))->toContain('late_drop_evidence')
        ->and((int) $period->dropped_events)->toBe(2);
});

it('a late batch updates a final hour and day: counts change and the state becomes corrected', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(5));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);
    rlpRun($this, RLP_FINALIZE_AT);
    expect(rlpPeriod('hour', RLP_HOUR)->state)->toBe('final');

    // An event for 16:30 arrives in a later minute file (a writer whose clock lagged).
    $writer = new SpoolWriter(rlpSpool($this), fn (): float => (RLP_MINUTE + 80) * 60 + 1.0);
    $writer->write(['t' => RLP_HOUR + 1800.0, 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'ip' => '203.0.113.200']);
    $report = rlpRun($this, (RLP_MINUTE + 82) * 60);
    $period = rlpPeriod('hour', RLP_HOUR);

    expect($report['late_periods'])->toBeGreaterThanOrEqual(1)
        ->and($period->state)->toBe('corrected')->and((int) $period->late_batches)->toBe(1)->and((int) $period->events)->toBe(6)
        ->and((int) $period->corrected_at)->toBe((RLP_MINUTE + 82) * 60)
        ->and(rlpEvents())->toBe(6)
        ->and((int) DB::connection('main')->table(T::BATCHES)->where('late_periods', '>', 0)->count())->toBe(1);
});

it('a period waits (provisional) for batches still in the spool, and becomes incomplete if they never arrive', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(5));
    rlpWrite($this, RLP_MINUTE + 1, rlpEventsN(5));
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);

    rlpRun($this, RLP_FINALIZE_AT, ['rollup_max_batches' => 1]);                  // one batch is left in sealed/
    expect(rlpPeriod('hour', RLP_HOUR)->state)->toBe('provisional');

    // It stays there (e.g. the rollup keeps failing on it) past the maximum wait.
    rlpRun($this, RLP_HOUR + 3600 + 3 * 3600, ['rollup_max_batches' => 1, 'finalize_max_wait_hours' => 2, 'rollup_time_budget_seconds' => 1], function (string $point) {
        if ($point === 'before_transaction') {
            throw new RuntimeException('keeps failing');
        }
    });
    expect(rlpPeriod('hour', RLP_HOUR)->state)->toBe('incomplete')
        ->and(json_decode(rlpPeriod('hour', RLP_HOUR)->reasons))->toContain('unprocessed_batches');
});

it('quarantines a batch with an unsupported (newer) schema version and marks its hour incomplete', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(3));
    file_put_contents($this->root.'/active/'.SpoolConfig::minuteKey(RLP_MINUTE).'.ndjson', '{"v":2,"t":1,"ev":"future","mod":"k"}'."\n", FILE_APPEND);
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);

    $report = rlpRun($this, RLP_FINALIZE_AT);

    expect($report['quarantined'])->toBe(1)->and(rlpEvents())->toBe(0)
        ->and(glob($this->root.'/quarantine/*.ndjson'))->toHaveCount(1)
        ->and(json_decode(rlpPeriod('hour', RLP_HOUR)->reasons))->toContain('quarantined_batch');
});

it('attributes malformed (torn) lines to their batch\'s hour, which then cannot be final', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(3));
    file_put_contents($this->root.'/active/'.SpoolConfig::minuteKey(RLP_MINUTE).'.ndjson', '{"v":1,"t":176', FILE_APPEND);
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);

    rlpRun($this, RLP_FINALIZE_AT);
    $period = rlpPeriod('hour', RLP_HOUR);

    expect((int) $period->malformed)->toBe(1)->and($period->state)->toBe('incomplete')
        ->and(json_decode($period->reasons))->toContain('malformed_lines')
        ->and(rlpEvents())->toBe(3);
});

it('a day is final only when all 24 hours are; hours before telemetry started make it incomplete', function () {
    $day = intdiv(RLP_HOUR, 86400) * 86400;
    rlpCover(intdiv($day, 60), intdiv($day, 60) + 1439);                            // full evidence for the whole day
    rlpRun($this, $day + 86400 + 7200 + 5);
    expect(rlpPeriod('day', $day)->state)->toBe('final');

    $next = $day + 86400;
    rlpCover(intdiv($next, 60) + 120, intdiv($next, 60) + 1439);                    // the next day misses 2 hours
    rlpRun($this, $next + 86400 + 7200 + 5);
    expect(rlpPeriod('day', $next)->state)->toBe('incomplete')
        ->and(json_decode(rlpPeriod('day', $next)->reasons))->toContain('hours_incomplete');
});

it('records coverage evidence for the minute it runs in, and acknowledges the drop ledger after finalizing', function () {
    rlpCover(RLP_MINUTE, RLP_MINUTE + 59);
    $report = rlpRun($this, RLP_FINALIZE_AT);

    expect(DB::connection('main')->table(T::COVERAGE)->where('minute', intdiv(RLP_FINALIZE_AT, 60))->value('spool_writable'))->toBeTruthy()
        ->and($report['finalizer']['acknowledged_minute'])->toBe(RLP_MINUTE + 59);
});

// --- Retention, dry run, off ----------------------------------------------------------------------------------------

it('bounds done/ by age and size; the ledger outlives the files, so a re-sealed old batch is still never re-applied', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(5));
    rlpWrite($this, RLP_MINUTE + 1, rlpEventsN(5));
    rlpRun($this, RLP_HOUR + 180);
    $files = glob($this->root.'/done/*.ndjson');
    $content = file_get_contents($files[0]);
    touch($files[0], RLP_HOUR - 2 * 86400);                                         // older than the 24 h retention

    $report = rlpRun($this, RLP_HOUR + 240);
    expect($report['done_deleted'])->toBe(1)->and(glob($this->root.'/done/*.ndjson'))->toHaveCount(1);

    $tiny = rlpRun($this, RLP_HOUR + 300, ['done_max_bytes' => 1]);                 // size cap
    expect($tiny['done_deleted'])->toBe(1)->and(glob($this->root.'/done/*.ndjson'))->toBe([]);

    file_put_contents($this->root.'/sealed/202511101600-99-0badc0de.ndjson', $content);   // the deleted batch, re-submitted
    expect(rlpRun($this, RLP_HOUR + 360)['duplicate_content'])->toBe(1)->and(rlpEvents())->toBe(10);
});

it('dry-run aggregates and reports, but writes nothing and moves nothing', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(7));
    $queries = [];
    DB::connection('main')->listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $report = rlpRun($this, RLP_HOUR + 180, ['aggregate' => 'dry-run']);

    expect($report['events'])->toBe(7)->and($report['dry_run'])->toHaveCount(1)
        ->and($queries)->toBe([])
        ->and(glob($this->root.'/sealed/*.ndjson'))->toHaveCount(1)
        ->and(glob($this->root.'/done/*.ndjson'))->toBe([]);
});

it('does nothing at all while aggregation is off (the shipped default)', function () {
    rlpWrite($this, RLP_MINUTE, rlpEventsN(3));

    expect(RollupConfig::fromLaravel()->mode)->toBe('off')
        ->and(rlpRun($this, RLP_HOUR + 180, ['aggregate' => 'off'])['ran'])->toBeFalse()
        ->and(glob($this->root.'/active/*.ndjson'))->toHaveCount(1);
});

it('validate is read-only and flags a done batch with no ledger row', function () {
    config(['download_telemetry.root' => $this->root, 'download_telemetry.spool' => 'on']);
    rlpWrite($this, RLP_MINUTE, rlpEventsN(3));
    rlpRun($this, RLP_HOUR + 180);
    expect(Artisan::call('downloads:telemetry-validate', ['--json' => true]))->toBe(0);

    DB::connection('main')->table(T::BATCHES)->delete();                           // simulate a lost ledger row
    $before = DB::connection('main')->table(T::METRICS_5M)->count();
    expect(Artisan::call('downloads:telemetry-validate', ['--json' => true]))->toBe(1)
        ->and(json_decode(Artisan::output(), true)['problems'][0])->toContain('has no ledger row')
        ->and(DB::connection('main')->table(T::METRICS_5M)->count())->toBe($before);
});
