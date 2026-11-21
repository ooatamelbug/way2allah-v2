<?php

namespace App\Support\Downloads\Telemetry;

/**
 * Local observability for the spool: size and file counts per state against the hard bound, the oldest unsealed
 * minute, the breaker, the drop ledger for a recent window (dropped events by reason, torn records repaired, slow
 * writes, health faults, worst latency) — and whether the RECORDED counters carry a known uncertainty (torn ledger
 * slots, a reboot window, unreadable shards, overwritten ring slots, drops this process could not record).
 * "No known uncertainty" is not proof that nothing was lost before being recorded (see `accounting_exact_scope`).
 * Read-only.
 */
final class SpoolStatus
{
    /**
     * @return array<string, mixed>
     */
    public static function collect(SpoolConfig $config, int $windowMinutes = 60, ?float $now = null): array
    {
        $now ??= microtime(true);
        $dirs = [];
        $totalBytes = 0;
        $totalFiles = 0;
        $oldestActive = null;

        foreach (['active' => $config->activeDir(), 'sealed' => $config->sealedDir(), 'quarantine' => $config->quarantineDir()] as $state => $dir) {
            $files = 0;
            $bytes = 0;
            foreach (@scandir($dir) ?: [] as $name) {
                if (! str_ends_with($name, '.ndjson')) {
                    continue;
                }
                $files++;
                $bytes += (int) @filesize($dir.'/'.$name);
                if ($state === 'active') {
                    $minute = SpoolConfig::minuteFromKey(substr($name, 0, -7));
                    $oldestActive = $minute !== null ? min($oldestActive ?? $minute, $minute) : $oldestActive;
                }
            }
            $dirs[$state] = ['files' => $files, 'bytes' => $bytes];
            $totalBytes += $bytes;
            $totalFiles += $files;
        }

        clearstatcache(true, $config->breakerPath());
        $breakerUntil = @filemtime($config->breakerPath());

        $ledger = (new DropLedger($config))->snapshot();
        $fromMinute = intdiv((int) $now, 60) - $windowMinutes + 1;
        $window = ['dropped_events' => 0, 'dropped_bytes' => 0, 'reasons' => [], 'torn_repaired' => 0, 'slow_writes' => 0,
            'health_faults' => 0, 'max_latency_us' => 0, 'uncertain_minutes' => 0];
        $mask = 0;
        foreach ($ledger['entries'] as $slot) {
            if ($slot['minute'] < $fromMinute) {
                continue;
            }
            foreach (['dropped_events', 'dropped_bytes', 'torn_repaired', 'slow_writes', 'health_faults'] as $k) {
                $window[$k] += $slot[$k];
            }
            $window['max_latency_us'] = max($window['max_latency_us'], $slot['max_latency_us']);
            $window['uncertain_minutes'] += $slot['uncertain'] ? 1 : 0;
            $mask |= $slot['reasons'];
        }
        $window['reasons'] = DropReason::names($mask);

        $uncertainty = array_filter([
            'torn_ledger_slots' => $ledger['torn_slots'],
            'reboot_windows' => count($ledger['reboots']),
            'unreadable_shards' => $ledger['shards_unreadable'],
            'overwritten_unacknowledged_events' => $ledger['overwritten_events'],
            'late_drop_evidence_pending' => $ledger['late_pending_events'],
            'unrecorded_in_this_process' => DropLedger::unrecorded(),
            'pending_in_this_process' => DropLedger::pendingEvents(),
        ]);

        return [
            'enabled' => $config->enabled,
            'root' => $config->root,
            'files' => $dirs,
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'max_files' => $config->maxFiles,
            'bound_bytes' => $config->spoolBoundBytes(),
            'used_ratio' => $config->spoolBoundBytes() > 0 ? round($totalBytes / $config->spoolBoundBytes(), 4) : 0.0,
            'ledger_bytes' => $config->ledgerBytes(),
            'oldest_active_minute' => $oldestActive === null ? null : SpoolConfig::minuteKey($oldestActive),
            'breaker_open_until' => $breakerUntil !== false && $breakerUntil > $now ? gmdate('c', $breakerUntil) : null,
            'drop_ledger' => [
                'shards' => $ledger['shards'],
                'slots' => $ledger['slots'],
                'window_minutes' => $windowMinutes,
                'window' => $window,
                'reboots' => $ledger['reboots'],
            ],
            // `accounting_exact` means: the drop counters that WERE recorded are consistent (no torn slot, no reboot
            // window, no unreadable shard, no overwritten slot, nothing pending or unrecorded in this process). It is
            // NOT a guarantee that every attempted event is accounted for: a process killed between deciding to drop an
            // event and recording that drop leaves no trace, so a clean ledger cannot prove that no such loss occurred.
            'accounting_uncertainty' => $uncertainty,
            'accounting_exact' => $uncertainty === [],
            'accounting_exact_scope' => 'recorded drop counters only, under the documented operational assumptions; a drop lost before it was recorded (process killed between the drop decision and the ledger write) is not detectable',
        ];
    }
}
