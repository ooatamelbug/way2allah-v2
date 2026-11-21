<?php

/*
 * Phase 3D / 3D.1 — archiving of download_rejections: day segments, state machine, retention boundaries, idempotent
 * repeated runs, concurrent inserts and workers, crashes at every stage, corruption, disk full, permission errors,
 * digest-bound backup confirmation (required by default), restore, per-day calendar-month expiration (month ends,
 * leap years), monitoring, storage estimation, and origin tracking (resource_title).
 * A "crash" is a checkpoint hook that throws: the run stops exactly there, with whatever it had made durable.
 */

use App\Support\Downloads\Archive\ArchiveConfig;
use App\Support\Downloads\Archive\ArchiveExpirer;
use App\Support\Downloads\Archive\ArchiveFiles;
use App\Support\Downloads\Archive\ArchiveJournal;
use App\Support\Downloads\Archive\ArchiveRestorer;
use App\Support\Downloads\Archive\ArchiveStatus;
use App\Support\Downloads\Archive\ArchiveStorageEstimator;
use App\Support\Downloads\Archive\RejectionArchiver;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Monitoring\DownloadMonitor;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const RA_DAY = 86400;

/** Tests opt INTO backup confirmation explicitly; production default is ON. */
function raConfig(object $t, array $o = []): ArchiveConfig
{
    return ArchiveConfig::fromArray($o + ['enabled' => true, 'directory' => $t->dir, 'chunk_rows' => 3, 'min_free_bytes' => 0, 'delete_pause_ms' => 0,
        'require_backup_confirmation' => false]);
}

function raArchiver(object $t, array $o = [], ?Closure $hook = null, ?int $now = null, ?Closure $disk = null): RejectionArchiver
{
    return new RejectionArchiver(raConfig($t, $o), DB::connection('main'), $hook, fn () => $now ?? $t->now, $disk);
}

/** Insert one rejection; returns its id. */
function raRow(int $createdAt, array $extra = []): int
{
    return (int) DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->insertGetId($extra + [
        'created_at' => $createdAt, 'reason' => 'DOWNLOAD_CAPACITY_FULL', 'pool' => 'global', 'module' => 'khotab', 'resource_type' => 'khotab_item',
        'resource_id' => 7, 'variant' => 'primary', 'requested_url' => 'https://way2allah.test/khotab-download-7.htm', 'file_size_source' => 'unknown',
        'client_ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 Test', 'active_downloads' => 400, 'download_limit' => 400, 'retry_after_seconds' => 60,
        'resource_title' => 'خطبة الجمعة',
    ]);
}

/** @return list<int> */
function raLiveIds(): array
{
    return DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
}

/** @return list<array<string, ?string>> every archived record, segment by segment */
function raRecords(ArchiveConfig $c, ?string $runId = null): array
{
    $records = [];
    foreach (ArchiveFiles::published($c, $runId) as $id) {
        foreach (ArchiveFiles::lines(ArchiveFiles::dataPath($c, $id)) as $line) {
            $records[] = ArchiveFiles::decode($line);
        }
    }

    return $records;
}

/** @return list<int> */
function raArchivedIds(ArchiveConfig $c): array
{
    return array_map(fn ($r) => (int) $r['id'], raRecords($c));
}

function raCrashAt(string $point, int $occurrence = 1): Closure
{
    $seen = 0;

    return function (string $p) use ($point, $occurrence, &$seen) {
        if ($p === $point && ++$seen === $occurrence) {
            throw new RuntimeException('simulated crash at '.$point);
        }
    };
}

beforeEach(function () {
    InMemoryConnection::setup('main', [DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections()]);
    $this->dir = sys_get_temp_dir().'/ra-'.bin2hex(random_bytes(5));
    $this->now = gmmktime(10, 0, 0, 11, 11, 2026);   // 2026-11-11 10:00 UTC
    $this->cutoff = $this->now - 13 * RA_DAY;          // exactly 13 days before the run: 2026-10-29 10:00 UTC
});

afterEach(function () {
    if (is_dir($this->dir)) {
        exec('chmod -R u+rwx '.escapeshellarg($this->dir).' && rm -rf '.escapeshellarg($this->dir));   // this test's own directory
    }
});

// --- Configuration and defaults -----------------------------------------------------------------------------------

it('is disabled by default, creates nothing, requires backup confirmation by default; 20 days / 7 days / 13-day cutoff / 4 months', function () {
    $shipped = (require base_path('config/download_rejections.php'))['archive'];
    $c = ArchiveConfig::fromArray(['directory' => $this->dir]);

    expect($shipped)->toMatchArray(['enabled' => false, 'frequency_days' => 7, 'active_retention_days' => 20, 'cutoff_days' => 13, 'retention_months' => 4,
        'require_backup_confirmation' => true])
        ->and($c->requireBackupConfirmation)->toBeTrue()
        ->and((new RejectionArchiver($c, DB::connection('main')))->run()['status'])->toBe('disabled')
        ->and(is_dir($this->dir))->toBeFalse()
        ->and(Artisan::call('downloads:rejections-archive'))->toBe(0)
        ->and(Artisan::output())->toContain('disabled')
        ->and((string) file_get_contents(base_path('routes/console.php')))->not->toContain('rejections-archive');
});

it('refuses settings that cannot keep the 20-day maximum, or an archive inside the public web root', function () {
    expect(raConfig($this, ['cutoff_days' => 14])->problems())->toContain('cutoff_days (14) + frequency_days (7) exceeds active_retention_days (20)')
        ->and(raArchiver($this, ['cutoff_days' => 14])->run()['status'])->toBe('refused')
        ->and(ArchiveConfig::fromArray(['enabled' => true, 'directory' => public_path('rejections')])->problems())
        ->toContain('archive directory must be outside the public web root');
});

// --- The happy path -------------------------------------------------------------------------------------------

it('initial archive: one segment per UTC day, ordered (created_at, id), verified, published atomically, then deleted', function () {
    $old = [raRow($this->now - 30 * RA_DAY), raRow($this->now - 20 * RA_DAY), raRow($this->cutoff - 1, ['user_agent' => "bad\xC3\x28utf8"]),
        raRow($this->now - 30 * RA_DAY - 60)];   // same day as the first, earlier time, higher id
    $edge = raRow($this->cutoff);                 // exactly at the cutoff: NOT archived
    $recent = [raRow($this->now - 2 * RA_DAY), raRow($this->now)];
    foreach (range(1, 4) as $i) {
        $old[] = raRow($this->now - (40 + $i) * RA_DAY);
    }
    $before = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->whereIn('id', $old)->orderBy('created_at')->orderBy('id')->get()
        ->map(fn ($r) => array_map(fn ($v) => $v === null ? null : (string) $v, (array) $r))->all();

    $report = raArchiver($this)->run();
    $c = raConfig($this);
    $segments = ArchiveFiles::published($c, $report['archive_id']);
    $days = array_unique(array_map(fn ($r) => gmdate('Ymd', (int) $r['created_at']), $before));

    expect($report)->toMatchArray(['status' => 'completed', 'state' => 'COMPLETED', 'rows' => 8, 'deleted' => 8, 'backlog' => false])
        ->and($report['segments'])->toBe($segments)->and($segments)->toHaveCount(count($days))
        ->and(array_map(fn ($s) => substr($s, -8), $segments))->toBe(array_values($days))
        ->and(raLiveIds())->toBe([$edge, ...$recent])
        ->and(raRecords($c))->toBe($before);   // every column, exact (invalid UTF-8 included), in (created_at, id) order

    foreach ($segments as $segment) {
        $m = ArchiveFiles::manifest($c, $segment);
        $lines = iterator_to_array(ArchiveFiles::lines(ArchiveFiles::dataPath($c, $segment)), false);
        $dayStart = gmmktime(0, 0, 0, (int) substr($segment, -4, 2), (int) substr($segment, -2), (int) substr($segment, -8, 4));
        expect($m)->toMatchArray(['format' => 'w2a-download-rejections-archive', 'version' => 2, 'archive_id' => $segment, 'run_id' => $report['archive_id'],
            'day' => gmdate('Y-m-d', $dayStart), 'rows' => count($lines), 'order' => 'created_at, id', 'cutoff' => $this->cutoff,
            'content_sha256' => hash('sha256', implode('', array_map(fn ($l) => $l."\n", $lines))),
            'file_sha256' => hash_file('sha256', ArchiveFiles::dataPath($c, $segment)),
            'expires_at' => gmdate('c', ArchiveFiles::deadline($dayStart, 4))])
            ->and(gmdate('Y-m-d', (int) $m['min_created_at']))->toBe($m['day'])->and(gmdate('Y-m-d', (int) $m['max_created_at']))->toBe($m['day'])
            ->and(sprintf('%o', fileperms(ArchiveFiles::dataPath($c, $segment)) & 0777))->toBe('640');
    }
    expect(glob($c->tmpDir().'/*'))->toBe([])
        ->and(sprintf('%o', fileperms($c->archivesDir()) & 0777))->toBe('750')->and(sprintf('%o', fileperms($this->dir) & 0777))->toBe('750');
});

it('repeated runs: not due within 7 days (a 30-minute-early scheduler still is); an empty run publishes nothing; never a duplicate', function () {
    raRow($this->now - 15 * RA_DAY);
    $recent = raRow($this->now - 5 * RA_DAY);
    expect(raArchiver($this)->run()['rows'])->toBe(1);

    expect(raArchiver($this)->run()['status'])->toBe('not_due')
        ->and(raArchiver($this, now: $this->now + 6 * RA_DAY)->run()['status'])->toBe('not_due')
        ->and(raArchiver($this, now: $this->now + 7 * RA_DAY - 3601)->run()['status'])->toBe('not_due');
    $early = raArchiver($this, now: $this->now + 7 * RA_DAY - 1800)->run();
    expect($early)->toMatchArray(['status' => 'completed', 'rows' => 0, 'segments' => []]);

    $week = raArchiver($this, now: $this->now + 16 * RA_DAY)->run();   // the 5-day-old row is now 21 days old
    expect($week)->toMatchArray(['status' => 'completed', 'rows' => 1])
        ->and(raLiveIds())->toBe([])
        ->and(raArchivedIds(raConfig($this)))->toHaveCount(2)->toContain($recent)
        ->and(array_unique(raArchivedIds(raConfig($this))))->toHaveCount(2);
});

it('a backlog larger than max_rows_per_run is archived over consecutive runs, each due at once', function () {
    foreach (range(1, 7) as $i) {
        raRow($this->now - 30 * RA_DAY);
    }
    $first = raArchiver($this, ['max_rows_per_run' => 4])->run();
    $second = raArchiver($this, ['max_rows_per_run' => 4])->run();   // not "not_due": backlog

    expect($first)->toMatchArray(['rows' => 4, 'backlog' => true])->and($second)->toMatchArray(['status' => 'completed', 'rows' => 3, 'backlog' => false])
        ->and(raLiveIds())->toBe([])->and(array_unique(raArchivedIds(raConfig($this))))->toHaveCount(7);
});

// --- Concurrency ----------------------------------------------------------------------------------------------

it('concurrent inserts during a run are never archived or deleted — even one with an old timestamp', function () {
    foreach (range(1, 6) as $i) {
        raRow($this->now - 30 * RA_DAY);
    }
    $inserted = [];
    $hook = function (string $p) use (&$inserted) {
        if ($p === 'copy_chunk' && $inserted === []) {
            $inserted[] = raRow($this->now);                   // a new refusal
            $inserted[] = raRow($this->now - 30 * RA_DAY);     // old created_at (clock skew) but id > high_id
        }
    };

    $report = raArchiver($this, hook: $hook)->run();

    expect($report)->toMatchArray(['rows' => 6, 'deleted' => 6])->and(raLiveIds())->toBe($inserted)
        ->and(array_intersect(raArchivedIds(raConfig($this)), $inserted))->toBe([]);
    expect(raArchiver($this, now: $this->now + 8 * RA_DAY)->run()['rows'])->toBe(1)->and(raLiveIds())->toBe([$inserted[0]]);
});

it('a second worker — a run or a backup confirmation — finds the archive lock held and changes nothing', function () {
    raRow($this->now - 30 * RA_DAY);
    $c = raConfig($this);
    ArchiveFiles::ensureDir($c, $this->dir);
    $held = fopen($c->lockPath(), 'c');
    flock($held, LOCK_EX);

    $report = raArchiver($this)->run();
    $confirm = raArchiver($this)->confirmBackup('rej-20261111T100000Z-00000000', str_repeat('a', 64));
    flock($held, LOCK_UN);
    fclose($held);

    expect($report['status'])->toBe('busy')->and($confirm)->toBe(['ok' => false, 'error' => 'an archive run is in progress; try again'])
        ->and(raLiveIds())->toHaveCount(1)->and(ArchiveJournal::all($c))->toBe([]);
});

// --- Crashes --------------------------------------------------------------------------------------------------

it('crash during copying: nothing published or deleted; the resumed run restarts the copy and archives every row exactly once', function () {
    $ids = array_map(fn ($i) => raRow($this->now - (30 + $i % 3) * RA_DAY), range(1, 7));

    $crashed = raArchiver($this, hook: raCrashAt('copy_chunk', 2))->run();
    $c = raConfig($this);
    expect($crashed)->toMatchArray(['status' => 'failed', 'state' => 'COPYING'])
        ->and($crashed['error'])->toBe('RuntimeException (code 0)')   // class only, never a message
        ->and(ArchiveFiles::published($c))->toBe([])->and(raLiveIds())->toBe($ids);

    $resumed = raArchiver($this)->run();
    expect($resumed)->toMatchArray(['status' => 'completed', 'archive_id' => $crashed['archive_id'], 'rows' => 7, 'deleted' => 7])
        ->and(raArchivedIds($c))->toEqualCanonicalizing($ids)->and(count(raArchivedIds($c)))->toBe(7)->and(raLiveIds())->toBe([]);
});

it('crash during verification or publication: recovered from scratch, one set of segments, no duplicates', function (string $point) {
    $ids = array_map(fn ($i) => raRow($this->now - (30 + $i % 2) * RA_DAY), range(1, 5));

    expect(raArchiver($this, hook: raCrashAt($point))->run()['state'])->toBe($point === 'verified' ? 'VERIFIED' : 'COPYING')
        ->and(raLiveIds())->toBe($ids);   // nothing deleted yet in any case
    $resumed = raArchiver($this)->run();

    expect($resumed['status'])->toBe('completed')->and(ArchiveFiles::published(raConfig($this)))->toHaveCount(2)
        ->and(raArchivedIds(raConfig($this)))->toEqualCanonicalizing($ids)->and(count(raArchivedIds(raConfig($this))))->toBe(5)->and(raLiveIds())->toBe([]);
})->with(['verify', 'verified_part', 'published_data', 'published', 'verified']);

it('crash during deletion: resumes at the recorded segment and chunk; every archived row deleted once, nothing else', function () {
    $ids = array_map(fn ($i) => raRow($this->now - (30 + $i % 2) * RA_DAY), range(1, 8));   // 2 segments × 4 rows
    $keep = raRow($this->now);

    $crashed = raArchiver($this, hook: raCrashAt('delete_chunk', 2))->run();
    expect($crashed['state'])->toBe('DELETING')->and(count(raLiveIds()))->toBe(9 - 4);   // first segment (3 + 1) deleted

    $resumed = raArchiver($this)->run();
    expect($resumed)->toMatchArray(['status' => 'completed', 'deleted' => 8])->and(raLiveIds())->toBe([$keep])
        ->and(raArchivedIds(raConfig($this)))->toEqualCanonicalizing($ids);
});

// --- Corruption and verification failures -------------------------------------------------------------------------

it('a corrupted published segment is caught before deletion: VERIFY_FAILED, nothing deleted, new runs blocked until abandoned', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 4));
    $crashed = raArchiver($this, hook: raCrashAt('deleting'))->run();   // published and verified, nothing deleted yet
    $c = raConfig($this);
    $segment = $crashed['segments'][0];
    $path = ArchiveFiles::dataPath($c, $segment);
    $bytes = file_get_contents($path);
    $bytes[(int) (strlen($bytes) / 2)] = chr(ord($bytes[(int) (strlen($bytes) / 2)]) ^ 0xFF);
    file_put_contents($path, $bytes);

    $resumed = raArchiver($this)->run();
    expect($resumed)->toMatchArray(['state' => 'VERIFY_FAILED', 'deleted' => 0])->and($resumed['error'])->toContain('checksum')
        ->and(raLiveIds())->toBe($ids)
        ->and(raArchiver($this, now: $this->now + 30 * RA_DAY)->run()['status'])->toBe('blocked')
        ->and(ArchiveStatus::collect($c, DB::connection('main'), $this->now, verify: true)['archives'][0]['integrity'])->toContain('file checksum mismatch')
        ->and((new ArchiveRestorer($c, DB::connection('main')))->restore($segment, false)['error'])->toContain('integrity check failed');

    expect(raArchiver($this)->abandon($crashed['archive_id']))->toBeTrue()
        ->and(glob($c->rejectedDir().'/*'))->toHaveCount(2)                   // kept for investigation, never deleted
        ->and(ArchiveFiles::published($c))->toBe([]);
    $again = raArchiver($this)->run();
    expect($again)->toMatchArray(['status' => 'completed', 'rows' => 4])->and(raArchivedIds($c))->toBe($ids)->and(raLiveIds())->toBe([]);
    expect((string) file_get_contents($c->auditLogPath()))->toContain('"action":"abandoned"');
});

it('a row that changes between copy and verification fails verification: nothing is published or deleted', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 3));
    $hook = function (string $p) use ($ids) {
        if ($p === 'copied') {
            DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->where('id', $ids[1])->update(['user_agent' => 'changed']);
        }
    };

    $report = raArchiver($this, hook: $hook)->run();

    expect($report)->toMatchArray(['state' => 'VERIFY_FAILED'])->and($report['error'])->toContain('differs from the table')
        ->and(ArchiveFiles::published(raConfig($this)))->toBe([])->and(raLiveIds())->toBe($ids);
});

// --- Disk full and permissions --------------------------------------------------------------------------------

it('disk full: the run stops before copying, nothing is published or deleted, and succeeds once space returns', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 3));

    $full = raArchiver($this, ['min_free_bytes' => 1024], disk: fn () => 10.0)->run();
    expect($full)->toMatchArray(['status' => 'failed', 'state' => 'COPYING', 'error' => 'insufficient free disk space for the archive'])
        ->and(ArchiveFiles::published(raConfig($this)))->toBe([])->and(raLiveIds())->toBe($ids);

    expect(raArchiver($this)->run())->toMatchArray(['status' => 'completed', 'rows' => 3]);
});

it('permission errors: an unwritable archive location fails cleanly and deletes nothing', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 2));
    mkdir($this->dir, 0750);
    chmod($this->dir, 0550);   // the archive location's PARENT is not writable
    if (is_writable($this->dir)) {
        $this->markTestSkipped('running as a user that ignores file permissions');
    }

    $report = raArchiver($this, ['directory' => $this->dir.'/archive'])->run();
    chmod($this->dir, 0750);

    expect($report['status'])->toBe('failed')->and($report['error'])->toContain('cannot be created')->and(raLiveIds())->toBe($ids);
});

// --- Backup confirmation (Phase 3D.1) -----------------------------------------------------------------------------

it('backup confirmation (default ON): a verified run waits — nothing deleted — across repeated runs, and reports its digest', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 2));
    $o = ['require_backup_confirmation' => true];

    $waiting = raArchiver($this, $o)->run();
    $c = raConfig($this, $o);
    expect($waiting)->toMatchArray(['status' => 'awaiting_backup', 'state' => 'VERIFIED'])
        ->and($waiting['digest'])->toBe(ArchiveFiles::digest($c, $waiting['segments']))->toMatch('/^[0-9a-f]{64}$/')
        ->and(raLiveIds())->toBe($ids)
        ->and(raArchiver($this, $o, now: $this->now + 3 * RA_DAY)->run()['status'])->toBe('awaiting_backup')
        ->and(raLiveIds())->toBe($ids)
        ->and(ArchiveStatus::collect($c, DB::connection('main'), $this->now)['awaiting_backup'][0])->toMatchArray(['run_id' => $waiting['archive_id'], 'digest' => $waiting['digest'], 'confirmed' => false]);
});

it('backup confirmation boundaries: wrong digest, wrong run, another run\'s digest, and a non-VERIFIED run are all refused', function () {
    $o = ['require_backup_confirmation' => true];
    raRow($this->now - 30 * RA_DAY);
    $first = raArchiver($this, $o)->run();
    expect(raArchiver($this, $o)->confirmBackup($first['archive_id'], $first['digest'])['ok'])->toBeTrue();
    expect(raArchiver($this, $o)->run()['status'])->toBe('completed');

    raRow($this->now - 30 * RA_DAY + 3600);
    $second = raArchiver($this, $o, now: $this->now + 7 * RA_DAY)->run();
    expect($second['status'])->toBe('awaiting_backup');
    $a = raArchiver($this, $o);

    expect($a->confirmBackup($second['archive_id'], str_repeat('0', 64)))->toMatchArray(['ok' => false, 'error' => "digest does not match this run's verified manifests"])
        ->and($a->confirmBackup($second['archive_id'], $first['digest'])['ok'])->toBeFalse()                       // another run's digest
        ->and($a->confirmBackup($first['archive_id'], $second['digest'])['error'])->toContain('COMPLETED, not VERIFIED')
        ->and($a->confirmBackup('rej-20991231T000000Z-deadbeef', $second['digest'])['error'])->toBe('no such archive run')
        ->and($a->confirmBackup($second['archive_id'], '')['ok'])->toBeFalse()
        ->and(raArchiver($this, $o, now: $this->now + 7 * RA_DAY)->run()['status'])->toBe('awaiting_backup')   // still waiting
        ->and(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->count())->toBe(1);
});

it('a confirmation is void if the archive changes afterwards: a rewritten manifest stops deletion; a corrupted file fails verification', function () {
    $o = ['require_backup_confirmation' => true];
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY), range(1, 3));
    $run = raArchiver($this, $o)->run();
    $c = raConfig($this, $o);
    expect(raArchiver($this, $o)->confirmBackup($run['archive_id'], $run['digest'], 'backup-job-42')['ok'])->toBeTrue();

    // The manifest is rewritten (same data, different bytes): the digest no longer matches → no deletion.
    $manifestPath = $c->archivesDir().'/'.$run['segments'][0].'.manifest.json';
    file_put_contents($manifestPath, json_encode(json_decode((string) file_get_contents($manifestPath), true)));
    $after = raArchiver($this, $o)->run();

    expect($after)->toMatchArray(['status' => 'awaiting_backup', 'state' => 'VERIFIED'])
        ->and($after['error'])->toContain('does not match the archive on disk')
        ->and(raLiveIds())->toBe($ids);
});

it('a confirmation persists across a crash during deletion: the resumed run finishes deleting without re-confirmation', function () {
    $o = ['require_backup_confirmation' => true];
    $ids = array_map(fn ($i) => raRow($this->now - (30 + $i % 2) * RA_DAY), range(1, 8));
    $run = raArchiver($this, $o)->run();
    raArchiver($this, $o)->confirmBackup($run['archive_id'], $run['digest']);

    $crashed = raArchiver($this, $o, hook: raCrashAt('delete_chunk', 1))->run();
    expect($crashed['state'])->toBe('DELETING')->and(count(raLiveIds()))->toBe(5);

    expect(raArchiver($this, $o)->run())->toMatchArray(['status' => 'completed', 'deleted' => 8])->and(raLiveIds())->toBe([])
        ->and(ArchiveJournal::find(raConfig($this, $o), $run['archive_id'])->data['backup_confirmation']['digest'])->toBe($run['digest']);
});

it('the command requires --digest with --confirm-backup and audits the confirmation with its reference', function () {
    raRow($this->now - 30 * RA_DAY);
    config(['download_rejections.archive' => ['enabled' => true, 'directory' => $this->dir, 'min_free_bytes' => 0, 'require_backup_confirmation' => true]]);
    Artisan::call('downloads:rejections-archive', ['--json' => true]);
    $run = json_decode(Artisan::output(), true);

    expect($run['status'])->toBe('awaiting_backup')
        ->and(Artisan::call('downloads:rejections-archive', ['--confirm-backup' => $run['archive_id']]))->toBe(1)
        ->and(Artisan::output())->toContain('needs --digest')
        ->and(Artisan::call('downloads:rejections-archive', ['--confirm-backup' => $run['archive_id'], '--digest' => str_repeat('f', 64)]))->toBe(1)
        ->and(Artisan::call('downloads:rejections-archive', ['--confirm-backup' => $run['archive_id'], '--digest' => $run['digest'], '--reference' => 'restic snapshot 9f2c']))->toBe(0);
    $audit = (string) file_get_contents(raConfig($this)->auditLogPath());
    expect($audit)->toContain('"action":"backup_confirmed"')->toContain($run['digest'])->toContain('restic snapshot 9f2c');
});

// --- Restore ----------------------------------------------------------------------------------------------------

it('restore: dry run changes nothing; a confirmed restore inserts into download_rejections_restored exactly; repeats are duplicates; changes are conflicts', function () {
    $ids = array_map(fn () => raRow($this->now - 30 * RA_DAY, ['user_agent' => "raw\xFF"]), range(1, 5));
    $original = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    $segment = raArchiver($this)->run()['segments'][0];
    $restorer = new ArchiveRestorer(raConfig($this), DB::connection('main'));

    $dry = $restorer->restore($segment, true);
    expect($dry)->toMatchArray(['ok' => true, 'dry_run' => true, 'rows' => 5, 'inserted' => 5])
        ->and(DB::connection('main')->getSchemaBuilder()->hasTable(ArchiveRestorer::TARGET))->toBeFalse();

    $real = $restorer->restore($segment, false);
    $restored = DB::connection('main')->table(ArchiveRestorer::TARGET)->orderBy('id')->get()->map(fn ($r) => array_map(fn ($v) => $v === null ? null : (string) $v, (array) $r))->all();
    expect($real)->toMatchArray(['ok' => true, 'inserted' => 5, 'duplicates' => 0])
        ->and($restored)->toBe(array_map(fn ($r) => array_map(fn ($v) => $v === null ? null : (string) $v, $r), $original))
        ->and(raLiveIds())->toBe([]);

    DB::connection('main')->table(ArchiveRestorer::TARGET)->where('id', $ids[0])->update(['client_ip' => '198.51.100.1']);
    expect($restorer->restore($segment, false))->toMatchArray(['inserted' => 0, 'duplicates' => 4, 'conflicts' => 1])
        ->and(DB::connection('main')->table(ArchiveRestorer::TARGET)->where('id', $ids[0])->value('client_ip'))->toBe('198.51.100.1');
});

it('the restore command requires explicit confirmation and never restores automatically', function () {
    raRow($this->now - 30 * RA_DAY);
    config(['download_rejections.archive' => ['enabled' => true, 'directory' => $this->dir, 'min_free_bytes' => 0, 'require_backup_confirmation' => false]]);
    $segment = raArchiver($this)->run()['segments'][0];

    expect(Artisan::call('downloads:rejections-archive-restore', ['archive' => $segment, '--no-interaction' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('Not confirmed')
        ->and(DB::connection('main')->getSchemaBuilder()->hasTable(ArchiveRestorer::TARGET))->toBeFalse()
        ->and(Artisan::call('downloads:rejections-archive-restore', ['archive' => $segment, '--dry-run' => true]))->toBe(0)
        ->and(Artisan::call('downloads:rejections-archive-restore', ['archive' => $segment, '--confirm' => 'rej-wrong']))->toBe(1)
        ->and(Artisan::call('downloads:rejections-archive-restore', ['archive' => $segment, '--confirm' => $segment]))->toBe(0)
        ->and(DB::connection('main')->table(ArchiveRestorer::TARGET)->count())->toBe(1);
});

// --- Expiration (Phase 3D.1: per record, by day segment) ------------------------------------------------------------

it('the deadline is 00:00 UTC on the creation date + 4 calendar months (month ends clamped, leap years) — never more than 4 months', function (string $created, string $deadline) {
    $t = strtotime($created.' UTC');
    $d = ArchiveFiles::deadline($t, 4);

    expect(gmdate('Y-m-d H:i:s', $d))->toBe($deadline)
        ->and($d)->toBeLessThanOrEqual(CarbonImmutable::createFromTimestampUTC($t)->addMonthsNoOverflow(4)->getTimestamp())   // never longer than 4 calendar months
        ->and(ArchiveFiles::deadline(strtotime(substr($created, 0, 10).' 00:00:00 UTC'), 4))->toBe($d)                               // the same for the whole day
        ->and(ArchiveFiles::deadline(strtotime(substr($created, 0, 10).' 23:59:59 UTC'), 4))->toBe($d);
})->with([
    ['2026-10-31 15:00:00', '2027-02-28 00:00:00'],
    ['2026-10-30 23:59:59', '2027-02-28 00:00:00'],
    ['2026-10-29 00:00:00', '2027-02-28 00:00:00'],   // Feb 29 does not exist in 2027 → clamped
    ['2026-10-28 12:00:00', '2027-02-28 00:00:00'],
    ['2026-11-01 00:00:01', '2027-03-01 00:00:00'],
    ['2027-01-31 08:00:00', '2027-05-31 00:00:00'],
    ['2027-10-31 08:00:00', '2028-02-29 00:00:00'],   // leap year
    ['2027-10-29 08:00:00', '2028-02-29 00:00:00'],
    ['2028-02-29 10:00:00', '2028-06-29 00:00:00'],   // from a leap day
    ['2026-08-31 10:00:00', '2026-12-31 00:00:00'],
    ['2026-12-31 23:00:00', '2027-04-30 00:00:00'],
]);

it('expiry deletes each day segment exactly at its records\' deadline: none early, none late (month end)', function () {
    $times = ['2026-10-30 23:00:00', '2026-10-31 01:00:00', '2026-10-31 23:59:00', '2026-11-01 00:00:00', '2026-11-01 13:00:00'];
    foreach ($times as $t) {
        raRow(strtotime($t.' UTC'));
    }
    $run = raArchiver($this, ['cutoff_days' => 0], now: strtotime('2026-11-05 12:00:00 UTC'))->run();
    $c = raConfig($this);
    expect($run['segments'])->toHaveCount(3);

    $check = function (int $at) use ($c) {
        (new ArchiveExpirer($c, fn () => $at))->run();
        foreach (raRecords($c) as $r) {
            expect(ArchiveFiles::deadline((int) $r['created_at'], 4))->toBeGreaterThan($at);   // nothing kept past its deadline …
        }

        return count(raRecords($c));
    };

    expect($check(strtotime('2027-02-27 23:59:59 UTC')))->toBe(5)                  // … and nothing deleted before it
        ->and($check(strtotime('2027-02-28 00:00:00 UTC')))->toBe(2)               // Oct 30 and Oct 31 (both → Feb 28)
        ->and($check(strtotime('2027-02-28 23:59:59 UTC')))->toBe(2)
        ->and($check(strtotime('2027-03-01 00:00:00 UTC')))->toBe(0)               // Nov 1 → Mar 1
        ->and((string) file_get_contents($c->auditLogPath()))->toContain('"action":"expired"')->toContain('"deadline":"2027-02-28T00:00:00+00:00"')
        ->and(ArchiveJournal::find($c, $run['archive_id'])->data['expired_segments'])->toHaveCount(3);
});

it('leap year: a segment from 2027-10-29 expires on 2028-02-29 00:00, not on Feb 28 and not on Mar 1', function () {
    raRow(strtotime('2027-10-29 06:00:00 UTC'));
    raArchiver($this, ['cutoff_days' => 0], now: strtotime('2027-11-03 00:00:00 UTC'))->run();
    $c = raConfig($this);

    expect((new ArchiveExpirer($c, fn () => strtotime('2028-02-28 23:59:59 UTC')))->run()['expired'])->toBe([])
        ->and((new ArchiveExpirer($c, fn () => strtotime('2028-02-29 00:00:00 UTC')))->run()['expired'])->toHaveCount(1);
});

it('only segments of a COMPLETED run expire (a run awaiting backup confirmation keeps them); expiry is bounded; dry run deletes nothing', function () {
    raRow(gmmktime(0, 0, 0, 1, 10, 2026));
    $waiting = raArchiver($this, ['require_backup_confirmation' => true, 'cutoff_days' => 0], now: gmmktime(0, 0, 0, 2, 1, 2026))->run();
    $c = raConfig($this);

    $report = (new ArchiveExpirer($c, fn () => gmmktime(0, 0, 0, 1, 1, 2027)))->run();
    expect($report['expired'])->toBe([])->and($report['skipped'])->toHaveKey($waiting['segments'][0])->and(ArchiveFiles::published($c))->toHaveCount(1);

    $o = ['require_backup_confirmation' => true, 'cutoff_days' => 0];
    raArchiver($this, $o, now: gmmktime(0, 0, 0, 2, 1, 2026))->confirmBackup($waiting['archive_id'], $waiting['digest']);
    raArchiver($this, $o, now: gmmktime(0, 0, 0, 2, 1, 2026))->run();
    raRow(gmmktime(0, 0, 0, 1, 20, 2026));
    raArchiver($this, ['cutoff_days' => 0], now: gmmktime(0, 0, 0, 2, 20, 2026))->run();
    $dry = (new ArchiveExpirer($c, fn () => gmmktime(0, 0, 0, 1, 1, 2027)))->run(true);
    expect($dry['expired'])->toHaveCount(2)->and(ArchiveFiles::published($c))->toHaveCount(2);
    $capped = (new ArchiveExpirer(raConfig($this, ['expire_max_archives_per_run' => 1]), fn () => gmmktime(0, 0, 0, 1, 1, 2027)))->run();
    expect($capped)->toMatchArray(['capped' => true])->and($capped['expired'])->toHaveCount(1)->and(ArchiveFiles::published($c))->toHaveCount(1);
});

// --- Retention semantics (live table) -----------------------------------------------------------------------------

it('retention boundaries: on schedule the live table never holds rows older than 20 days; a missed run exceeds it (and is monitored)', function () {
    // Weekly runs on schedule over 8 weeks, LATE in the day (23:00), a refusal every hour: just before each run the
    // oldest row is ≤ 20 days old. (A run that fires LATE adds its lateness to this — the documented exception.)
    $start = gmmktime(23, 0, 0, 11, 1, 2026);
    $worst = 0.0;
    $previous = null;
    for ($week = 0; $week < 8; $week++) {
        $runAt = $start + $week * 7 * RA_DAY;
        for ($t = ($week === 0 ? $runAt - 7 * RA_DAY : $previous); $t < $runAt; $t += 3600) {
            raRow($t);
        }
        $oldest = (int) DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->min('created_at');
        $worst = max($worst, ($runAt - $oldest) / RA_DAY);
        expect(raArchiver($this, now: $runAt)->run()['status'])->toBe('completed');
        expect(($runAt - (int) DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->min('created_at')) / RA_DAY)->toBeLessThanOrEqual(13.0);
        $previous = $runAt;
    }
    expect($worst)->toBeLessThanOrEqual(20.0);

    $late = $start + 8 * 7 * RA_DAY + 7 * RA_DAY;
    for ($t = $previous; $t < $late; $t += 3600) {
        raRow($t);
    }
    $status = ArchiveStatus::collect(raConfig($this), DB::connection('main'), $late);
    expect($status['oldest_active_age_days'])->toBeGreaterThan(20.0)->and($status['backlog_rows'])->toBeGreaterThan(0);
});

// --- Monitoring -------------------------------------------------------------------------------------------------

function raMonitor(object $t, int $now, array $o = []): array
{
    $settings = ['spool' => 'off', 'aggregate' => 'off', 'events' => 'off'];

    return (new DownloadMonitor(DB::connection('main'), SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings), [], $t->dir.'-slots', 4, false,
        '/proc/locks', null, raConfig($t, $o)))->collect($now)['checks']['rejections_archive'];
}

it('monitoring: disabled, never run, healthy, 8/14 days, interrupted, verification failure, awaiting backup, expired backlog', function () {
    $settings = ['spool' => 'off', 'aggregate' => 'off', 'events' => 'off'];
    $disabled = (new DownloadMonitor(DB::connection('main'), SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings), [], $this->dir, 4, false,
        '/proc/locks', null, ArchiveConfig::fromArray(['directory' => $this->dir])))->collect($this->now)['checks']['rejections_archive'];
    expect($disabled['status'])->toBe('disabled')
        ->and(raMonitor($this, $this->now))->toMatchArray(['status' => 'warn', 'problems' => ['no_successful_run_yet']]);

    raRow($this->now - 15 * RA_DAY);
    raArchiver($this)->run();
    $ok = raMonitor($this, $this->now + RA_DAY);
    expect($ok)->toMatchArray(['status' => 'ok', 'archive_count' => 1, 'backlog_rows' => 0])->and($ok['archive_bytes'])->toBeGreaterThan(0)
        ->and(raMonitor($this, $this->now + 8 * RA_DAY))->toMatchArray(['status' => 'warn', 'problems' => ['no_successful_run_for_8_days']])
        ->and(raMonitor($this, $this->now + 14 * RA_DAY)['status'])->toBe('critical');

    raRow($this->now - 15 * RA_DAY);
    raArchiver($this, hook: raCrashAt('copy_chunk'), now: $this->now + 7 * RA_DAY)->run();
    expect(raMonitor($this, $this->now + 7 * RA_DAY)['problems'])->toContain('failed_or_interrupted_run');
    $c = raConfig($this);
    $j = ArchiveJournal::all($c)[1];
    $j->set(['state' => ArchiveJournal::VERIFY_FAILED, 'error' => 'verification failed: test'], $this->now);
    expect(raMonitor($this, $this->now + 7 * RA_DAY))->toMatchArray(['status' => 'critical'])
        ->and(raMonitor($this, $this->now + 7 * RA_DAY)['problems'])->toContain('verification_failed');

    $j->set(['state' => ArchiveJournal::VERIFIED, 'digest' => str_repeat('a', 64)], $this->now);
    expect(raMonitor($this, $this->now + 7 * RA_DAY)['problems'])->toContain('awaiting_backup_confirmation');

    $j->set(['state' => ArchiveJournal::ABANDONED], $this->now);
    expect(raMonitor($this, gmmktime(0, 0, 0, 6, 1, 2027))['problems'])->toContain('expired_archives_present');
});

it('inspection is read-only: no directory created, no lock taken; lists segments with day and deadline; --verify checks integrity', function () {
    config(['download_rejections.archive' => ['enabled' => false, 'directory' => $this->dir]]);
    expect(Artisan::call('downloads:rejections-archive-inspect', ['--json' => true]))->toBe(0)->and(is_dir($this->dir))->toBeFalse();

    raRow($this->now - 30 * RA_DAY);
    $run = raArchiver($this)->run();
    config(['download_rejections.archive' => ['enabled' => true, 'directory' => $this->dir]]);
    Artisan::call('downloads:rejections-archive-inspect', ['--json' => true, '--verify' => true]);
    $json = json_decode(Artisan::output(), true);
    $segment = $run['segments'][0];

    expect($json['archives'][0])->toMatchArray(['archive_id' => $segment, 'run_id' => $run['archive_id'], 'state' => 'COMPLETED', 'rows' => 1, 'integrity' => 'ok',
        'day' => gmdate('Y-m-d', $this->now - 30 * RA_DAY), 'expires_at' => gmdate('c', ArchiveFiles::deadline($this->now - 30 * RA_DAY, 4))])
        ->and(Artisan::call('downloads:rejections-archive-inspect', ['archive' => $segment]))->toBe(0)
        ->and(Artisan::output())->toContain('"content_sha256"');
});

// --- Storage estimation (Phase 3D.1) ------------------------------------------------------------------------------

it('estimates storage read-only from a bounded sample compressed in memory, with stated confidence', function () {
    foreach (range(1, 300) as $i) {
        raRow($this->now - ($i % 10) * RA_DAY - $i, ['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64) Chrome/'.(100 + $i % 30), 'client_ip' => '198.51.100.'.($i % 250)]);
    }
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $e = (new ArchiveStorageEstimator(raConfig($this), DB::connection('main')))->estimate($this->now, sample: 100, days: 10, backupCopies: 2);

    expect($e)->toMatchArray(['sampled_rows' => 100, 'compressed_is_assumed' => false, 'window_days' => 10, 'backup_copies' => 2])
        ->and($e['compressed_bytes_per_row'])->toBeGreaterThan(5)->toBeLessThan($e['raw_ndjson_bytes_per_row'])
        ->and($e['rows_per_day_avg'])->toBe(30.0)
        ->and($e['retention_window_days_max'])->toBeGreaterThanOrEqual(123)
        ->and($e['backup_bytes_peak'])->toBe($e['archive_steady_state_bytes_peak'] * 2)
        ->and($e['recommended_free_bytes_on_archive_disk'])->toBe($e['archive_steady_state_bytes_peak'] + $e['run_temporary_bytes_peak'] + $e['disk_reserve_bytes'])
        ->and(collect($queries)->first(fn ($s) => str_contains($s, 'order by "id" desc limit 100')))->not->toBeNull()   // bounded PK read
        ->and(collect($queries)->filter(fn ($s) => preg_match('/^\s*(insert|update|delete)/i', $s) === 1))->toBeEmpty()
        ->and(is_dir($this->dir))->toBeFalse();                                                                     // nothing written

    $empty = (new ArchiveStorageEstimator(raConfig($this), DB::connection('main')));
    DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->delete();
    expect($empty->estimate($this->now))->toMatchArray(['sampled_rows' => 0, 'compressed_is_assumed' => true, 'compressed_bytes_per_row' => 120.0])
        ->and($empty->estimate($this->now)['confidence'])->toContain('ASSUMED');
});

// --- Origin tracking ----------------------------------------------------------------------------------------------

it('archives every existing column plus resource_title', function () {
    raRow($this->now - 30 * RA_DAY);
    $segment = raArchiver($this)->run()['segments'][0];
    $columns = ArchiveFiles::manifest(raConfig($this), $segment)['columns'];

    expect($columns)->toBe(DB::connection('main')->getSchemaBuilder()->getColumnListing(DownloadRejectionRecorder::TABLE))
        ->and($columns)->toContain('requested_url', 'resolved_download_url', 'client_ip', 'forwarded_for', 'user_agent', 'referer', 'resource_title')
        ->and(raRecords(raConfig($this))[0]['resource_title'])->toBe('خطبة الجمعة');
});
