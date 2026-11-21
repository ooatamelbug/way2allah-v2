<?php

/*
 * Phase 2 — HyperLogLog accuracy and merge semantics, and the shared telemetry schema (one definition for the
 * migration and the tests; additive; no legacy table).
 */

use App\Support\Downloads\Telemetry\Database\TelemetrySchema;
use App\Support\Downloads\Telemetry\HyperLogLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InMemoryConnection;

it('estimates distinct counts within the HyperLogLog error bound (p = 12, σ ≈ 1.6 %)', function (int $n) {
    $hll = new HyperLogLog;
    for ($i = 0; $i < $n; $i++) {
        $hll->add("203.0.113.{$i}|ua-".($i * 7919));
    }
    $error = abs($hll->estimate() - $n) / $n;

    expect($error)->toBeLessThan(0.05);   // > 3σ margin, deterministic hash so not flaky
    fwrite(STDERR, sprintf("\n[HLL] n=%d estimate=%d error=%.2f%%", $n, $hll->estimate(), 100 * $error));
})->with([1_000, 10_000, 100_000, 1_000_000]);

it('is near-exact for small sets (linear counting) and zero when empty', function () {
    $hll = new HyperLogLog;
    expect($hll->estimate())->toBe(0)->and($hll->isEmpty())->toBeTrue();
    foreach (range(1, 100) as $i) {
        $hll->add("v{$i}");
        $hll->add("v{$i}");   // duplicates do not count
    }
    expect(abs($hll->estimate() - 100))->toBeLessThanOrEqual(2);
});

it('merges commutatively and idempotently: any order, any repetition, same sketch', function () {
    $a = new HyperLogLog;
    $b = new HyperLogLog;
    foreach (range(1, 5000) as $i) {
        $a->add("a{$i}");
        $b->add("b{$i}");
    }
    $ab = new HyperLogLog($a->registers());
    $ab->merge($b);
    $ba = new HyperLogLog($b->registers());
    $ba->merge($a);
    $twice = new HyperLogLog($ab->registers());
    $twice->merge($b);
    $twice->merge($a);

    expect($ab->registers())->toBe($ba->registers())->and($twice->registers())->toBe($ab->registers())
        ->and(abs($ab->estimate() - 10_000) / 10_000)->toBeLessThan(0.05);
});

it('rejects registers of the wrong size', function () {
    expect(fn () => new HyperLogLog('short'))->toThrow(InvalidArgumentException::class);
});

it('defines seven additive tables once, used by the migration itself', function () {
    $migration = (string) file_get_contents(base_path('database/migrations/2026_11_11_100000_create_download_telemetry_tables.php'));

    expect(array_keys(TelemetrySchema::tables()))->toBe([
        'download_telemetry_batches', 'download_metrics_5m', 'download_metrics_daily', 'download_ip_ua_hourly',
        'download_distinct_sketches', 'download_period_status', 'download_telemetry_coverage',
    ])
        ->and($migration)->toContain('TelemetrySchema::tables()')
        ->and($migration)->toContain('NonEmptyTableGuard::assertSafeToDrop')
        ->and($migration)->not->toMatch('/Schema::[a-z]+\([^)]*\)->(table|drop)\(\s*[\'"]nuke_/')   // never alters a legacy table
        ->and($migration)->not->toContain('->table(');

    InMemoryConnection::setup('main', TelemetrySchema::tables());
    foreach (array_keys(TelemetrySchema::tables()) as $table) {
        expect(Schema::connection('main')->hasTable($table))->toBeTrue();
    }
    expect(Schema::connection('main')->getColumnListing('download_ip_ua_hourly'))
        ->toContain('ip', 'ua_hash', 'ua_family', 'resources', 'resources_overflow')
        ->not->toContain('user_id', 'users');
});

it('stores a HyperLogLog sketch as 4,096 bytes and reads it back intact (binary-safe)', function () {
    InMemoryConnection::setup('main', TelemetrySchema::tables());
    $hll = new HyperLogLog;
    foreach (range(1, 20_000) as $i) {
        $hll->add("x{$i}");
    }
    DB::connection('main')->table('download_distinct_sketches')->insert(['day' => 1, 'dimension' => 'ip', 'registers' => $hll->registers(), 'updated_at' => 1]);

    $back = (string) DB::connection('main')->table('download_distinct_sketches')->value('registers');
    expect(strlen($back))->toBe(HyperLogLog::REGISTERS)->and($back)->toBe($hll->registers());
});
