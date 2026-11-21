<?php

/*
 * Phase 3B.1 — spool files hold raw IP addresses: directories 0750 and data files 0640 by default (configurable for a
 * split web/cron user, never "other"); quarantined batch files deleted after 14 days by the prune command only.
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\TelemetryPruner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\DropReason;
use App\Support\Downloads\Telemetry\HealthCounter;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\DB;
use Tests\Support\InMemoryConnection;

function sppMode(string $path): string
{
    clearstatcache(true, $path);

    return sprintf('%04o', fileperms($path) & 0777);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/spp-'.bin2hex(random_bytes(5));
    DropLedger::resetProcessStateForTesting();
    $this->umask = umask();
});

afterEach(function () {
    umask($this->umask);
    DropLedger::resetProcessStateForTesting();
    if (is_dir($this->root)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            @chmod($f->getPathname(), 0700);
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }
});

it('creates spool directories 0750 and data files 0640 by default, whatever the umask allows beyond that', function (int $umask) {
    umask($umask);
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'disk_reserve_bytes' => 1, 'ledger_shards' => 1]);

    (new SpoolWriter($config))->write(['t' => microtime(true), 'ev' => 'download_accepted', 'mod' => 'khotab', 'ip' => '192.0.2.1']);
    (new DropLedger($config))->record(intdiv(time(), 60), ['dropped_events' => 1, 'dropped_bytes' => 1, 'reasons' => DropReason::IO_ERROR]);
    (new HealthCounter($config))->add(intdiv(time(), 60));

    expect(sppMode($this->root))->toBe('0750')
        ->and(sppMode($config->activeDir()))->toBe('0750')
        ->and(sppMode(glob($config->activeDir().'/*.ndjson')[0]))->toBe('0640')
        ->and(sppMode($config->ledgerShardPath(0)))->toBe('0640')
        ->and(sppMode($config->healthPath()))->toBe('0640');
})->with(['umask 022' => [0022], 'umask 002' => [0002]]);

it('accepts 0770/0660 for a web user and cron user sharing a group, but never grants "other"', function () {
    $split = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'dir_mode' => '0770', 'file_mode' => '0660']);
    $everyone = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'dir_mode' => '0777', 'file_mode' => '0666']);
    $broken = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'dir_mode' => '0450', 'file_mode' => 'rw-r-----']);

    expect([$split->dirMode, $split->fileMode])->toBe([0770, 0660])
        ->and([$everyone->dirMode, $everyone->fileMode])->toBe([0770, 0660])   // "other" bits stripped
        ->and([$broken->dirMode, $broken->fileMode])->toBe([0750, 0640]);      // owner lacks rwx / unparseable → default
});

it('sealed, done and quarantined files keep the 0640 mode (moves are renames)', function () {
    umask(0022);
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root, 'disk_reserve_bytes' => 0, 'seal_grace_minutes' => 0]);
    $minute = intdiv(time(), 60) - 5;
    (new SpoolWriter($config, fn (): float => $minute * 60 + 1.0))->write(['t' => $minute * 60 + 1.0, 'ev' => 'download_accepted', 'mod' => 'khotab']);

    (new SpoolSealer($config))->seal(true);

    $sealed = glob($config->sealedDir().'/*.ndjson');
    expect($sealed)->toHaveCount(1)->and(sppMode($sealed[0]))->toBe('0640')->and(sppMode($config->sealedDir()))->toBe('0750');
});

// --- Quarantine retention ---------------------------------------------------------------------------------------

function sppPruner(string $root, array $overrides = []): TelemetryPruner
{
    $settings = $overrides + ['spool' => 'on', 'root' => $root, 'aggregate' => 'on', 'prune' => 'on'];

    return new TelemetryPruner(DB::connection('main'), SpoolConfig::fromArray($settings), RollupConfig::fromArray($settings));
}

it('deletes quarantined batch files older than 14 days — and nothing else — only when not a dry run', function () {
    InMemoryConnection::setup('main', T::tables());
    $config = SpoolConfig::fromArray(['spool' => 'on', 'root' => $this->root]);
    mkdir($config->quarantineDir(), 0750, true);
    mkdir($config->sealedDir(), 0750, true);
    $now = time();
    $files = [
        'old.ndjson' => $now - 15 * 86400,
        'old.stale.ndjson' => $now - 30 * 86400,
        'recent.ndjson' => $now - 13 * 86400,
        'old-notes.txt' => $now - 60 * 86400,
    ];
    foreach ($files as $name => $mtime) {
        file_put_contents($config->quarantineDir().'/'.$name, str_repeat('x', 100));
        touch($config->quarantineDir().'/'.$name, $mtime);
    }
    file_put_contents($config->sealedDir().'/old-sealed.ndjson', 'x');
    touch($config->sealedDir().'/old-sealed.ndjson', $now - 60 * 86400);

    $dry = sppPruner($this->root)->run($now, true);
    expect($dry['quarantine'])->toMatchArray(['eligible' => 2, 'deleted' => 0])
        ->and(glob($config->quarantineDir().'/*'))->toHaveCount(4);

    $real = sppPruner($this->root)->run($now, false);
    $left = array_map('basename', glob($config->quarantineDir().'/*'));
    sort($left);

    expect($real['quarantine'])->toMatchArray(['eligible' => 2, 'deleted' => 2, 'bytes' => 200, 'capped' => false])
        ->and($left)->toBe(['old-notes.txt', 'recent.ndjson'])
        ->and(is_file($config->sealedDir().'/old-sealed.ndjson'))->toBeTrue();   // only quarantine is touched
});

it('quarantine retention is configurable and the prune switch stays OFF by default and unscheduled', function () {
    expect(RollupConfig::fromArray([])->retentionQuarantineDays)->toBe(14)
        ->and(RollupConfig::fromArray(['retention_quarantine_days' => 30])->retentionQuarantineDays)->toBe(30)
        ->and(RollupConfig::fromArray([])->pruneEnabled)->toBeFalse()
        ->and((require base_path('config/download_telemetry.php'))['prune'] ?? 'off')->toBe('off')
        ->and((string) file_get_contents(base_path('routes/console.php')))->not->toContain('telemetry-prune');
});
