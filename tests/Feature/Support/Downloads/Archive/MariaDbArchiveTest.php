<?php

/*
 * Phase 3D on a DISPOSABLE MariaDB 10.11 (W2A_MARIADB_TEST_*; skipped otherwise): the additive migration's
 * compatibility (instant ADD COLUMN on a production-shaped table), cross-connection GET_LOCK exclusion, a multi-chunk
 * archive with concurrent inserts from another session, crash and resume during deletion, restore into a LIKE copy
 * (same keys and indexes), and the monitoring queries' index use. Never production.
 */

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveFiles;
use App\Support\Downloads\Archive\ArchiveRestorer;
use App\Support\Downloads\Archive\ArchiveStatus;
use App\Support\Downloads\Archive\ArchiveStorageEstimator;
use App\Support\Downloads\Archive\RejectionArchiver;
use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;

function mraConfig(): ?array
{
    $host = getenv('W2A_MARIADB_TEST_HOST');
    if ($host === false || $host === '') {
        return null;
    }

    return ['driver' => 'mysql', 'host' => $host, 'port' => (string) getenv('W2A_MARIADB_TEST_PORT'), 'database' => (string) getenv('W2A_MARIADB_TEST_DATABASE'),
        'username' => (string) getenv('W2A_MARIADB_TEST_USERNAME'), 'password' => (string) getenv('W2A_MARIADB_TEST_PASSWORD'),
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => null];
}

function mraArchive(object $t, ?Closure $hook = null, array $o = []): array
{
    return mraArchiver($t, $hook, $o)->run();
}

function mraArchiver(object $t, ?Closure $hook = null, array $o = []): RejectionArchiver
{
    return new RejectionArchiver(ArchiveConfig::fromArray($o + ['enabled' => true, 'directory' => $t->dir, 'chunk_rows' => 5000, 'min_free_bytes' => 0, 'delete_pause_ms' => 0,
        'require_backup_confirmation' => false]), DB::connection('main'), $hook, fn () => $t->now);
}

function mraSeed(int $n, int $createdAt, bool $withTitle = true): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = ['created_at' => $createdAt + $i % 3600, 'reason' => 'DOWNLOAD_CAPACITY_FULL', 'pool' => 'global', 'module' => 'khotab', 'resource_type' => 'khotab_item',
            'resource_id' => $i % 500, 'variant' => $i % 5 === 0 ? 'mirror' : 'primary', 'variant_id' => $i % 5 === 0 ? $i : null,
            'requested_url' => "https://way2allah.test/khotab-download-{$i}.htm", 'resolved_download_url' => "https://ia800.us.archive.org/{$i}/x.mp4?sig=abc",
            'file_size_source' => 'unknown', 'client_ip' => '203.0.'.intdiv($i % 65000, 255).'.'.($i % 255), 'user_agent' => 'Mozilla/5.0 (X11) '.($i % 7),
            'referer' => 'https://way2allah.test/khotab-item-'.($i % 500).'.htm', 'active_downloads' => 400, 'download_limit' => 400, 'retry_after_seconds' => 60,
        ] + ($withTitle ? ['resource_title' => 'محاضرة '.($i % 500)] : []);
    }
    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->insert($chunk);
    }
}

beforeEach(function () {
    if (mraConfig() === null) {
        $this->markTestSkipped('No disposable MariaDB configured (W2A_MARIADB_TEST_*).');
    }
    config(['database.connections.main' => mraConfig(), 'database.connections.mra2' => mraConfig()]);
    DB::purge('main');
    DB::purge('mra2');
    expect(DB::connection('main')->selectOne('select version() as v')->v)->toContain('MariaDB');
    foreach ([ArchiveRestorer::TARGET, DownloadRejectionRecorder::TABLE] as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    // Production shape: the creating migration's table (fixture minus the new column) …
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, function ($table) {
        MainSchema::downloadRejections()($table);
        $table->index('created_at', 'idx_dr_created');
        $table->index(['module', 'resource_id', 'created_at'], 'idx_dr_resource');
        $table->index(['client_ip', 'created_at'], 'idx_dr_ip');
        $table->index(['reason', 'created_at'], 'idx_dr_reason');
    });
    $this->dir = sys_get_temp_dir().'/mra-'.bin2hex(random_bytes(5));
    $this->now = gmmktime(10, 0, 0, 11, 11, 2026);
});

afterEach(function () {
    if (! isset($this->dir)) {
        return;
    }
    foreach ([ArchiveRestorer::TARGET, DownloadRejectionRecorder::TABLE] as $table) {
        Schema::connection('main')->dropIfExists($table);
    }
    DB::purge('main');
    DB::purge('mra2');
    exec('rm -rf '.escapeshellarg($this->dir));   // this test's own directory
});

it('the additive migration is an INSTANT ADD COLUMN on MariaDB 10.11 and yields exactly the fixture column', function () {
    DB::connection('main')->statement('ALTER TABLE download_rejections DROP COLUMN resource_title');   // the table as production has it today
    mraSeed(2000, $this->now - 30 * 86400, withTitle: false);

    DB::connection('main')->statement('ALTER TABLE download_rejections ADD COLUMN resource_title VARCHAR(255) NULL, ALGORITHM=INSTANT');
    $column = DB::connection('main')->selectOne("SELECT COLUMN_TYPE, IS_NULLABLE, CHARACTER_SET_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'download_rejections' AND column_name = 'resource_title'");

    expect($column->COLUMN_TYPE)->toBe('varchar(255)')->and($column->IS_NULLABLE)->toBe('YES')->and($column->CHARACTER_SET_NAME)->toBe('utf8mb4')
        ->and(DB::connection('main')->table('download_rejections')->whereNotNull('resource_title')->count())->toBe(0)   // existing rows: NULL
        ->and(DB::connection('main')->table('download_rejections')->count())->toBe(2000);                               // nothing else changed
});

it('a second session holding the archive GET_LOCK makes the run busy and changes nothing', function () {
    mraSeed(10, $this->now - 30 * 86400);
    expect((int) DB::connection('mra2')->selectOne('SELECT GET_LOCK(?, 0) AS l', [RejectionArchiver::DB_LOCK])->l)->toBe(1);

    $report = mraArchive($this);
    DB::connection('mra2')->selectOne('SELECT RELEASE_LOCK(?) AS r', [RejectionArchiver::DB_LOCK]);

    expect($report['status'])->toBe('busy')->and(DB::connection('main')->table('download_rejections')->count())->toBe(10)
        ->and(mraArchive($this)['status'])->toBe('completed');
});

it('archives 12,000 rows in 5,000-row chunks while another session inserts; only archived ids are deleted', function () {
    mraSeed(12_000, $this->now - 30 * 86400);
    $recent = 300;
    mraSeed($recent, $this->now - 86400);
    $concurrent = [];
    $hook = function (string $p) use (&$concurrent) {
        if ($p === 'copy_chunk') {
            $concurrent[] = (int) DB::connection('mra2')->table('download_rejections')->insertGetId(['created_at' => $this->now - 40 * 86400, 'reason' => 'DOWNLOAD_CAPACITY_FULL',
                'pool' => 'global', 'module' => 'khotab', 'resource_type' => 'khotab_item', 'resource_id' => 1, 'variant' => 'primary', 'requested_url' => '/x',
                'file_size_source' => 'unknown', 'client_ip' => '198.51.100.1', 'active_downloads' => 400, 'download_limit' => 400, 'retry_after_seconds' => 60]);
        }
    };

    $t = microtime(true);
    $report = mraArchive($this, $hook);
    $seconds = microtime(true) - $t;
    $config = ArchiveConfig::fromArray(['directory' => $this->dir]);
    $archived = [];
    $bytes = 0;
    foreach ($report['segments'] as $segment) {
        $bytes += filesize(ArchiveFiles::dataPath($config, $segment));
        foreach (ArchiveFiles::lines(ArchiveFiles::dataPath($config, $segment)) as $l) {
            $archived[] = (int) ArchiveFiles::decode($l)['id'];
        }
    }

    expect($report)->toMatchArray(['status' => 'completed', 'rows' => 12_000, 'deleted' => 12_000])
        ->and($concurrent)->toHaveCount(3)
        ->and(array_intersect($archived, $concurrent))->toBe([])
        ->and(DB::connection('main')->table('download_rejections')->count())->toBe($recent + 3)
        ->and(DB::connection('main')->table('download_rejections')->whereIn('id', $concurrent)->count())->toBe(3);
    expect(count($archived))->toBe(12_000)->and(array_unique($archived))->toHaveCount(12_000)
        ->and($report['segments'])->toHaveCount(1);   // seeded within one hour of one day
    fwrite(STDERR, sprintf("MRA_PERF rows=12000 seconds=%.2f file_bytes=%d bytes_per_row=%.1f\n", $seconds, $bytes, $bytes / 12000));
});

it('crash during deletion on MariaDB: resumed, every archived row deleted exactly once', function () {
    mraSeed(11_000, $this->now - 30 * 86400);
    $seen = 0;
    $crashed = mraArchive($this, function (string $p) use (&$seen) {
        if ($p === 'delete_chunk' && ++$seen === 1) {
            throw new RuntimeException('simulated crash');
        }
    });
    expect($crashed['state'])->toBe('DELETING')->and(DB::connection('main')->table('download_rejections')->count())->toBe(6000);

    expect(mraArchive($this))->toMatchArray(['status' => 'completed', 'deleted' => 11_000])
        ->and(DB::connection('main')->table('download_rejections')->count())->toBe(0);
});

it('restore on MariaDB creates download_rejections_restored LIKE the live table (same keys and indexes) and restores exactly', function () {
    mraSeed(1500, $this->now - 30 * 86400);
    $before = DB::connection('main')->table('download_rejections')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $id = mraArchive($this)['segments'][0];

    $report = (new ArchiveRestorer(ArchiveConfig::fromArray(['enabled' => true, 'directory' => $this->dir]), DB::connection('main')))->restore($id, false);
    $indexes = fn (string $t) => collect(DB::connection('main')->select("SHOW INDEX FROM `{$t}`"))->pluck('Key_name')->unique()->sort()->values()->all();
    $after = DB::connection('main')->table(ArchiveRestorer::TARGET)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    expect($report)->toMatchArray(['ok' => true, 'inserted' => 1500, 'conflicts' => 0])
        ->and($indexes(ArchiveRestorer::TARGET))->toBe($indexes('download_rejections'))
        ->and($after)->toEqual($before);
});

it('the archive status queries use idx_dr_created', function () {
    mraSeed(3000, $this->now - 30 * 86400);
    mraSeed(3000, $this->now - 86400);
    DB::connection('main')->statement('ANALYZE TABLE download_rejections');
    $cutoff = ArchiveConfig::fromArray([])->cutoff($this->now);

    $plan = collect(DB::connection('main')->select('EXPLAIN SELECT COUNT(*) AS c FROM (SELECT 1 FROM download_rejections WHERE created_at < ? LIMIT '.ArchiveStatus::COUNT_CAP.') AS b', [$cutoff]))
        ->firstWhere('table', 'download_rejections');
    $min = collect(DB::connection('main')->select('EXPLAIN SELECT MIN(created_at) FROM download_rejections'))->first();
    $status = ArchiveStatus::collect(ArchiveConfig::fromArray(['enabled' => true, 'directory' => $this->dir]), DB::connection('main'), $this->now);

    expect($plan->key)->toBe('idx_dr_created')->and($plan->type)->toBe('range')
        ->and($min->Extra)->toContain('optimized away')
        ->and($status['backlog_rows'])->toBe(3000)->and(is_dir($this->dir))->toBeFalse();   // read-only: nothing created
});

it('day segments on MariaDB: 40 days of rows → one segment per day; the (created_at, id) keyset query uses idx_dr_created', function () {
    foreach (range(20, 59) as $daysAgo) {
        mraSeed(250, intdiv($this->now, 86400) * 86400 - $daysAgo * 86400 + 3600);
    }
    DB::connection('main')->statement('ANALYZE TABLE download_rejections');
    $cutoff = $this->now - 13 * 86400;
    $plan = collect(DB::connection('main')->select('EXPLAIN SELECT id FROM download_rejections WHERE id <= ? AND created_at < ? AND created_at >= ? AND (created_at > ? OR id > ?) ORDER BY created_at, id LIMIT 5000',
        [PHP_INT_MAX, $cutoff, 1, 1, 0]))->firstWhere('table', 'download_rejections');

    $report = mraArchive($this);
    $config = ArchiveConfig::fromArray(['directory' => $this->dir]);

    expect($plan->key)->toBe('idx_dr_created')->and($plan->Extra)->not->toContain('filesort')
        ->and($report)->toMatchArray(['status' => 'completed', 'rows' => 10_000, 'deleted' => 10_000])
        ->and($report['segments'])->toHaveCount(40);
    foreach ($report['segments'] as $segment) {
        $m = ArchiveFiles::manifest($config, $segment);
        expect($m['rows'])->toBe(250)->and(gmdate('Y-m-d', (int) $m['min_created_at']))->toBe($m['day'])->and(gmdate('Y-m-d', (int) $m['max_created_at']))->toBe($m['day']);
    }
});

it('backup confirmation on MariaDB: deletion waits for the exact digest; a wrong one deletes nothing', function () {
    mraSeed(3000, $this->now - 30 * 86400);
    $o = ['require_backup_confirmation' => true];
    $waiting = mraArchive($this, null, $o);

    expect($waiting['status'])->toBe('awaiting_backup')
        ->and(mraArchiver($this, null, $o)->confirmBackup($waiting['archive_id'], hash('sha256', 'wrong'))['ok'])->toBeFalse()
        ->and(mraArchive($this, null, $o)['status'])->toBe('awaiting_backup')
        ->and(DB::connection('main')->table('download_rejections')->count())->toBe(3000)
        ->and(mraArchiver($this, null, $o)->confirmBackup($waiting['archive_id'], $waiting['digest'], 'snapshot-1')['ok'])->toBeTrue()
        ->and(mraArchive($this, null, $o))->toMatchArray(['status' => 'completed', 'deleted' => 3000])
        ->and(DB::connection('main')->table('download_rejections')->count())->toBe(0);
});

it('the storage estimate on MariaDB reads statistics and a bounded primary-key sample only', function () {
    mraSeed(5000, $this->now - 5 * 86400);
    DB::connection('main')->statement('ANALYZE TABLE download_rejections');
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = [$q->sql, $q->bindings];
    });

    $e = (new ArchiveStorageEstimator(ArchiveConfig::fromArray(['enabled' => true, 'directory' => $this->dir]), DB::connection('main')))
        ->estimate($this->now, 2000, 30);

    foreach ($queries as [$sql, $bindings]) {
        if (! preg_match('/^\s*select/i', $sql) || str_contains($sql, 'information_schema')) {
            continue;
        }
        foreach (DB::connection('main')->select('EXPLAIN '.$sql, $bindings) as $row) {
            if (($row->table ?? null) === 'download_rejections') {
                expect($row->type)->not->toBe('ALL', "full scan: {$sql}");
            }
        }
    }
    expect($e)->toMatchArray(['sampled_rows' => 2000, 'compressed_is_assumed' => false])->and($e['innodb_bytes_per_row'])->toBeInt()
        ->and($e['table_rows_estimate'])->toBeInt()->and(is_dir($this->dir))->toBeFalse();
    fwrite(STDERR, 'MRA_ESTIMATE '.json_encode(array_intersect_key($e, array_flip(['compressed_bytes_per_row', 'raw_ndjson_bytes_per_row', 'innodb_bytes_per_row', 'rows_per_day_avg', 'archive_steady_state_bytes_peak', 'confidence']))).PHP_EOL);
});
