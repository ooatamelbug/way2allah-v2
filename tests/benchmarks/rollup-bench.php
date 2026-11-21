<?php

/*
 * Phase 2 rollup benchmark (manual; not part of the test suite):
 *
 *   php tests/benchmarks/rollup-bench.php [minutes=10]
 *
 * For each load level it writes `minutes` one-minute batches through the real spool writer (realistic events: an IP
 * pool, a User-Agent pool, 50,000 resources, three event types), then times ONE rollup run applying all of them to a
 * scratch SQLite FILE database (created and deleted in the system temp directory). Reported: apply time per batch,
 * database statements by type, rows per table, and database file growth. SQLite is a stand-in: MariaDB timings will
 * differ, the statement and row counts will not.
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$minutes = (int) ($argv[1] ?? 10);
$levels = [1_000 => 2_000, 10_000 => 5_000, 60_000 => 20_000];   // events per minute => IP pool size
$startMinute = 29_379_840;

printf("%-10s %8s %9s %10s %10s %10s %8s %8s %8s %8s %8s %9s\n", 'ev/minute', 'events', 'ms/batch', 'stmts/bat', 'selects', 'inserts', 'm5m', 'daily', 'ip_ua', 'sketch', 'period', 'sqlite growth');

foreach ($levels as $perMinute => $ipPool) {
    $dir = sys_get_temp_dir().'/w2a-rollup-bench-'.bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    $dbFile = $dir.'/bench.sqlite';
    touch($dbFile);
    $mariadb = getenv('W2A_BENCH_MARIADB') === '1';   // the disposable MariaDB from W2A_MARIADB_TEST_* (never production)
    config(['database.connections.main' => $mariadb
        ? ['driver' => 'mysql', 'host' => getenv('W2A_MARIADB_TEST_HOST'), 'port' => getenv('W2A_MARIADB_TEST_PORT'), 'database' => getenv('W2A_MARIADB_TEST_DATABASE'),
            'username' => getenv('W2A_MARIADB_TEST_USERNAME'), 'password' => getenv('W2A_MARIADB_TEST_PASSWORD'), 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => null]
        : ['driver' => 'sqlite', 'database' => $dbFile, 'prefix' => '', 'foreign_key_constraints' => false, 'busy_timeout' => 15000]]);
    DB::purge('main');
    foreach (array_reverse(array_keys(T::tables())) as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    foreach (T::tables() as $table => $definition) {
        Schema::connection('main')->create($table, $definition);
    }
    $settings = ['spool' => 'on', 'root' => $dir.'/spool', 'disk_reserve_bytes' => 0, 'seal_grace_minutes' => 0, 'ledger_shards' => 16,
        'aggregate' => 'on', 'connection' => 'main', 'rollup_max_batches' => 1000, 'rollup_time_budget_seconds' => 3600,
        'file_max_bytes' => 64 * 1024 * 1024];
    $spool = SpoolConfig::fromArray($settings);
    $events = ['capacity_rejected', 'transfer_started', 'transfer_finished', 'redirect_issued'];

    for ($m = 0; $m < $minutes; $m++) {
        $minute = $startMinute + $m;
        $writer = new SpoolWriter($spool, fn (): float => $minute * 60 + 1.0);
        for ($i = 0; $i < $perMinute; $i++) {
            $ev = $events[$i % 4];
            $writer->write(array_filter([
                't' => $minute * 60 + ($i % 60) + 0.5, 'ev' => $ev, 'mod' => $i % 10 === 0 ? 'telawah' : 'khotab', 'rt' => 'item',
                'rid' => random_int(1, 50_000), 'var' => $i % 3 === 0 ? 'mirror' : 'primary', 'ip' => '10.'.intdiv($i % $ipPool, 65536).'.'.intdiv($i % $ipPool, 256) % 256 .'.'.($i % $ipPool) % 256,
                'uah' => 'ua'.($i % 50), 'uaf' => 'browser', 'sh' => $ev === 'transfer_finished' ? 'archive.org' : null,
                'st' => $ev === 'transfer_finished' ? 'completed' : null, 'b' => $ev === 'transfer_finished' ? random_int(1, 50_000_000) : null,
                'd' => $ev === 'transfer_finished' ? random_int(100, 4_000_000) : null, 'pa' => 400, 'pl' => 400,
            ], fn ($v) => $v !== null));
        }
    }

    $counts = ['select' => 0, 'insert' => 0, 'update' => 0, 'other' => 0];
    DB::connection('main')->listen(function ($q) use (&$counts) {
        $verb = strtolower(strtok(ltrim($q->sql), ' '));
        $counts[isset($counts[$verb]) ? $verb : 'other']++;
    });
    $sizeBefore = filesize($dbFile);
    $t0 = microtime(true);
    $report = (new RollupRunner($spool, RollupConfig::fromArray($settings), null, fn (): float => ($startMinute + $minutes + 1) * 60.0))->run();
    $elapsed = microtime(true) - $t0;
    clearstatcache();
    $db = DB::connection('main');
    $statements = array_sum($counts);

    printf("%-10d %8d %9.1f %10.1f %10d %10d %8d %8d %8d %8d %8d %8.1fM\n", $perMinute, $report['events'], 1000 * $elapsed / max(1, $report['applied']),
        $statements / max(1, $report['applied']), $counts['select'], $counts['insert'], $db->table(T::METRICS_5M)->count(), $db->table(T::METRICS_DAILY)->count(),
        $db->table(T::IP_UA_HOURLY)->count(), $db->table(T::SKETCHES)->count(), $db->table(T::PERIODS)->count(), (filesize($dbFile) - $sizeBefore) / 1048576);
    if ($report['errors'] !== []) {
        echo '  errors: '.json_encode($report['errors'])."\n";
    }
    if ($mariadb) {   // InnoDB storage per table (after ANALYZE, so the statistics are current)
        foreach (array_keys(T::tables()) as $table) {
            $db->statement("ANALYZE TABLE `{$table}`");
        }
        foreach ($db->select("select table_name t, table_rows r, data_length d, index_length i from information_schema.tables where table_schema = database() and table_name like 'download%' order by table_name") as $row) {
            $rows = max(1, (int) $db->table($row->t)->count());
            printf("    %-28s rows %7d  data %8.1f KiB  index %7.1f KiB  ≈ %5d B/row\n", $row->t, $rows, $row->d / 1024, $row->i / 1024, intdiv((int) $row->d + (int) $row->i, $rows));
        }
    }

    DB::purge('main');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}
printf("peak memory %.1f MiB, load %.1f\n", memory_get_peak_usage(true) / 1048576, sys_getloadavg()[0]);
