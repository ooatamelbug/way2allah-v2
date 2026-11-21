<?php

namespace App\Console\Commands;

use App\Support\Downloads\Telemetry\Aggregation\PeriodFinalizer;
use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY consistency and quality report for Phase 2 telemetry:
 *  - every batch in done/ has a ledger row with the same SHA-256 (else: inconsistency);
 *  - batches in sealed/ that already have a ledger row (a crash between commit and move — the next rollup moves them);
 *  - quarantined batches;
 *  - period quality for the last N days (counts per state and reasons), coverage gaps in the last 24 h;
 *  - labels: exact counts only for final/corrected periods; daily distinct figures are HyperLogLog ESTIMATES.
 * Exit code 1 on any inconsistency.
 */
class DownloadsTelemetryValidate extends Command
{
    protected $signature = 'downloads:telemetry-validate {--days=2 : Days of period quality to summarise} {--json : Print JSON}';

    protected $description = 'Read-only consistency and data-quality report for download telemetry aggregation';

    public function handle(): int
    {
        $spool = SpoolConfig::fromLaravel();
        $db = DB::connection(RollupConfig::fromLaravel()->connection);
        $problems = [];

        $done = [];
        foreach (glob($spool->doneDir().'/*.ndjson') ?: [] as $path) {
            $done[basename($path, '.ndjson')] = $path;
        }
        $ledger = [];
        foreach (array_chunk(array_keys($done), 500) as $chunk) {
            foreach ($db->table(T::BATCHES)->whereIn('batch_id', $chunk)->get(['batch_id', 'sha256']) as $row) {
                $ledger[$row->batch_id] = $row->sha256;
            }
        }
        foreach ($done as $id => $path) {
            if (! isset($ledger[$id])) {
                // a duplicate-content batch is moved to done/ under its own id without a ledger row of its own
                $sha = (string) hash_file('sha256', $path);
                if (! $db->table(T::BATCHES)->where('sha256', $sha)->exists()) {
                    $problems[] = "done/{$id} has no ledger row (not applied)";
                }
            } elseif (hash_file('sha256', $path) !== $ledger[$id]) {
                $problems[] = "done/{$id} differs from the content recorded in the ledger";
            }
        }

        $awaitingMove = [];
        $sealed = array_map(fn ($p) => basename($p, '.ndjson'), glob($spool->sealedDir().'/*.ndjson') ?: []);
        foreach (array_chunk($sealed, 500) as $chunk) {
            foreach ($db->table(T::BATCHES)->whereIn('batch_id', $chunk)->pluck('batch_id') as $id) {
                $awaitingMove[] = $id;
            }
        }

        $since = intdiv(time(), 86400) * 86400 - max(1, (int) $this->option('days')) * 86400;
        $periods = [];
        foreach ($db->table(T::PERIODS)->where('period_start', '>=', $since)->get() as $row) {
            $key = $row->granularity.':'.$row->state;
            $periods[$key] = ($periods[$key] ?? 0) + 1;
            foreach ((array) json_decode((string) $row->reasons, true) as $reason) {
                $periods['reason:'.$reason] = ($periods['reason:'.$reason] ?? 0) + 1;
            }
        }
        $fromMinute = intdiv(time(), 60) - 1440;
        $covered = $db->table(T::COVERAGE)->where('minute', '>=', $fromMinute)->where('spool_enabled', true)->where('spool_writable', true)->count();

        $report = [
            'consistent' => $problems === [],
            'problems' => $problems,
            'sealed_waiting' => count($sealed),
            'sealed_already_applied_awaiting_move' => $awaitingMove,
            'done_files' => count($done),
            'quarantined' => array_map('basename', glob($spool->quarantineDir().'/*.ndjson') ?: []),
            'periods' => $periods,
            'coverage_minutes_last_24h' => $covered,
            'labels' => [
                'counts' => 'exact for captured events only in '.PeriodFinalizer::FINAL.'/'.PeriodFinalizer::CORRECTED.' periods; lower bounds in '.PeriodFinalizer::INCOMPLETE.' periods; may still change while '.PeriodFinalizer::PROVISIONAL,
                'hourly_distinct' => 'exact distinct IP addresses and IP/User-Agent combinations (never people) from download_ip_ua_hourly',
                'daily_distinct' => 'APPROXIMATE (HyperLogLog, ± ~1.6 %)',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Consistent: '.($report['consistent'] ? 'yes' : 'NO'));
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            $this->line(sprintf('sealed waiting %d (already applied, awaiting move: %d), done %d, quarantined %d, coverage minutes (24 h) %d',
                $report['sealed_waiting'], count($awaitingMove), $report['done_files'], count($report['quarantined']), $covered));
            foreach ($periods as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
