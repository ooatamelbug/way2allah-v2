<?php

/*
 * Child process for Phase 2 crash and concurrency tests: boots the application, points `main` at a shared SQLite
 * FILE database, and runs one rollup. Spec (JSON file):
 *   id, barrier, db (sqlite path) or db_config (connection array), isolation (optional, e.g. "READ COMMITTED"),
 *   config (download_telemetry settings), now (unix time),
 *   crash_at  — SIGKILL itself at this checkpoint (a real crash: no rollback code, no shutdown functions)
 *   pause     — list of checkpoints to stop at once (see SpoolProcess)
 *   exclusive — false bypasses rollup.lock (to test the ledger's own protection)
 *   gate      — wait for "{barrier}/go" first
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$base = dirname(__DIR__, 3);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$spec = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$barrier = $spec['barrier'];
$id = $spec['id'];
$pauses = array_flip($spec['pause'] ?? []);

// `db_config` (any connection, e.g. a disposable MariaDB) or `db` (an SQLite file).
config(['database.connections.main' => $spec['db_config'] ?? ['driver' => 'sqlite', 'database' => $spec['db'], 'prefix' => '', 'foreign_key_constraints' => false, 'busy_timeout' => 15000]]);
DB::purge('main');
if (isset($spec['isolation'])) {   // e.g. "READ COMMITTED", to test the lost-update risk under weaker isolation
    DB::connection('main')->statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$spec['isolation']);
}

$waitFor = static function (string $file): void {
    $until = microtime(true) + 60;
    while (! file_exists($file)) {
        if (microtime(true) > $until) {
            fwrite(STDERR, "timeout waiting for {$file}\n");
            exit(3);
        }
        usleep(500);
    }
};

$hook = static function (string $point, array $context = []) use ($spec, &$pauses, $barrier, $id, $waitFor): void {
    if (isset($context['session_id'])) {   // the advisory-lock session, so a test can kill exactly that session
        file_put_contents("{$barrier}/{$id}.session", (string) $context['session_id']);
    }
    if (($spec['crash_at'] ?? null) === $point) {
        posix_kill(getmypid(), SIGKILL);
    }
    if (isset($pauses[$point])) {
        unset($pauses[$point]);
        touch("{$barrier}/{$id}.{$point}.reached");
        $waitFor("{$barrier}/{$id}.{$point}.release");
    }
};

if (! empty($spec['gate'])) {
    touch("{$barrier}/{$id}.ready");
    $waitFor("{$barrier}/go");
}

$runner = new RollupRunner(SpoolConfig::fromArray($spec['config']), RollupConfig::fromArray($spec['config']), $hook,
    fn (): float => (float) $spec['now'], $spec['exclusive'] ?? true);
echo json_encode($runner->run());
