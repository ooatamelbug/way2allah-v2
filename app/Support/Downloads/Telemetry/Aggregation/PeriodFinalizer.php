<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use Illuminate\Database\Connection;

/**
 * Data quality per period — assigned only from EVIDENCE, never merely because every visible batch was processed.
 *
 * Hour H (UTC), evaluated once H's end + `finalize_hour_grace_seconds` has passed:
 *  - provisional  → it stays provisional while active/sealed spool files for H's minutes still wait to be applied
 *                   (up to `finalize_max_wait_hours`; then: incomplete, `unprocessed_batches`);
 *  - final        → ALL of: every one of H's 60 minutes has coverage evidence (spool enabled and writable, observed by
 *                   the rollup); no batch for H is quarantined; the drop ledger records no dropped event for H, no
 *                   uncertain minute, no unreadable shard and no reboot window touching H, and still holds H's
 *                   minutes (ring not wrapped); no malformed line was attributed to H;
 *  - incomplete   → otherwise, with every failing condition in `reasons`. Counts are then lower bounds.
 * Day D, evaluated once D's end + `finalize_day_grace_seconds` has passed: final when all 24 hours are final or
 * corrected; incomplete when any hour is incomplete or missing (e.g. before telemetry started); provisional while any
 * hour is.
 *
 * Later evidence is never ignored: a late batch turns final into `corrected` (BatchApplier); drop or uncertainty
 * evidence found in a finalized hour's ledger slots within 24 h turns it `incomplete` (`late_drop_evidence`,
 * `ledger_uncertain`); a drop recorded for an already-acknowledged minute at ANY later time is flagged by the
 * ledger and reopens the affected periods (applyLateEvidence); provisional periods older than the look-back window
 * become `incomplete` (`expired`). Coverage: a minute counts only when the spool was enabled AND operational —
 * otherwise `spool_disabled`, `spool_unwritable`, `breaker_open`, `spool_full`, or `coverage_gap` (no evidence).
 *
 * Distinct counts: exact distinct IP addresses / IP-User-Agent combinations per hour come from
 * download_ip_ua_hourly and are exact only for `final` (or `corrected`) hours; daily distinct figures are HyperLogLog
 * ESTIMATES and are always labelled approximate.
 */
final class PeriodFinalizer
{
    public const PROVISIONAL = 'provisional';

    public const FINAL = 'final';

    public const INCOMPLETE = 'incomplete';

    public const CORRECTED = 'corrected';

    public function __construct(
        private readonly Connection $db,
        private readonly SpoolConfig $spool,
        private readonly RollupConfig $config,
    ) {}

    public static function addReason(string $json, string $reason): string
    {
        $decoded = json_decode($json, true);
        $reasons = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        if (! in_array($reason, $reasons, true)) {
            $reasons[] = $reason;
        }
        sort($reasons);

        return (string) json_encode($reasons);
    }

    /** @return array{hours_final: int, hours_incomplete: int, hours_rechecked_incomplete: int, late_evidence_hours: int, days_final: int, days_incomplete: int, expired: int, acknowledged_minute: ?int} */
    public function run(int $now): array
    {
        $report = ['hours_final' => 0, 'hours_incomplete' => 0, 'hours_rechecked_incomplete' => 0, 'late_evidence_hours' => 0, 'days_final' => 0,
            'days_incomplete' => 0, 'expired' => 0, 'acknowledged_minute' => null];

        $firstMinute = $this->db->table(T::COVERAGE)->min('minute');
        if ($firstMinute === null) {
            return $report;   // no evidence has ever been recorded: nothing can be finalized
        }

        $ledger = new DropLedger($this->spool);
        $currentMinute = intdiv($now, 60);
        $from = max(intdiv((int) $firstMinute * 60, 3600) * 3600, intdiv($now - $this->config->lookbackHours * 3600, 3600) * 3600);
        $lastHour = intdiv($now - $this->config->hourGraceSeconds, 3600) * 3600 - 3600;   // hours whose end + grace has passed
        $files = $this->spoolFilesByMinute();

        // A minute is covered only when the spool was enabled AND operational (see TelemetrySchema::coverage()).
        $coverage = [];
        $notCovered = [];   // minute => reason
        foreach ($this->db->table(T::COVERAGE)->whereBetween('minute', [intdiv($from, 60), intdiv($lastHour + 3600, 60) - 1])->get() as $c) {
            $reason = match (true) {
                ! (bool) $c->spool_enabled => 'spool_disabled',
                ! (bool) $c->spool_writable => 'spool_unwritable',
                (bool) $c->breaker_open => 'breaker_open',
                (bool) $c->spool_full => 'spool_full',
                default => null,
            };
            if ($reason === null) {
                $coverage[(int) $c->minute] = true;
            } else {
                $notCovered[(int) $c->minute] = $reason;
            }
        }

        $dayFrom = intdiv($from, 86400) * 86400;
        $hours = [];
        foreach ($this->db->table(T::PERIODS)->where('granularity', 'hour')->where('period_start', '>=', $dayFrom)->get() as $row) {
            $hours[(int) $row->period_start] = (array) $row;
        }

        $earliestProvisional = null;
        for ($h = $from; $h <= $lastHour; $h += 3600) {
            $row = $hours[$h] ?? self::blankRow('hour', $h, $now);
            $first = intdiv($h, 60);
            $last = $first + 59;

            if (in_array($row['state'], [self::FINAL, self::CORRECTED], true)) {
                if ((int) $row['finalized_at'] >= $now - 86400) {
                    $evidence = $ledger->evidence($first, $last, $currentMinute);
                    $lateReason = $evidence['dropped_events'] > (int) $row['dropped_events'] ? 'late_drop_evidence'
                        : (self::uncertain($evidence) ? 'ledger_uncertain' : null);
                    if ($lateReason !== null) {
                        $row['state'] = self::INCOMPLETE;
                        $row['dropped_events'] = $evidence['dropped_events'];
                        $row['reasons'] = self::addReason((string) $row['reasons'], $lateReason);
                        $row['updated_at'] = $now;
                        $this->save($row);
                        $hours[$h] = $row;
                        $report['hours_rechecked_incomplete']++;
                    }
                }

                continue;
            }
            if ($row['state'] === self::INCOMPLETE) {
                continue;
            }

            $reasons = [];
            $pending = false;
            for ($m = $first; $m <= $last; $m++) {
                $pending = $pending || isset($files['pending'][$m]);
            }
            if ($pending) {
                if ($now < $h + 3600 + $this->config->maxWaitHours * 3600) {
                    $earliestProvisional ??= $h;
                    if (! isset($hours[$h]) || $row['reasons'] !== '["awaiting_batches"]') {
                        $row['reasons'] = '["awaiting_batches"]';   // visible, not just absent
                        $row['updated_at'] = $now;
                        $this->save($row);
                        $hours[$h] = $row;
                    }

                    continue;   // stays provisional: batches for this hour still wait to be applied
                }
                $reasons[] = 'unprocessed_batches';
            }

            $covered = 0;
            $quarantined = false;
            for ($m = $first; $m <= $last; $m++) {
                if (isset($coverage[$m])) {
                    $covered++;
                } else {
                    $reasons[] = $notCovered[$m] ?? 'coverage_gap';   // no evidence at all for this minute
                }
                $quarantined = $quarantined || isset($files['quarantine'][$m]);
            }
            $reasons = array_values(array_unique($reasons));
            if ($quarantined) {
                $reasons[] = 'quarantined_batch';
            }

            $evidence = $ledger->evidence($first, $last, $currentMinute);
            if ($evidence['wrapped']) {
                $reasons[] = 'drop_ledger_wrapped';
            }
            if ($evidence['dropped_events'] > 0) {
                $reasons[] = 'dropped_events';
            }
            if (self::uncertain($evidence)) {
                $reasons[] = 'ledger_uncertain';
            }
            if ((int) $row['malformed'] > 0) {
                $reasons[] = 'malformed_lines';
            }

            sort($reasons);
            $row['state'] = $reasons === [] ? self::FINAL : self::INCOMPLETE;
            $row['coverage_minutes'] = $covered;
            $row['dropped_events'] = $evidence['dropped_events'];
            $row['reasons'] = (string) json_encode($reasons);
            $row['finalized_at'] = $now;
            $row['updated_at'] = $now;
            $this->save($row);
            $hours[$h] = $row;
            $report[$row['state'] === self::FINAL ? 'hours_final' : 'hours_incomplete']++;
        }

        $report['late_evidence_hours'] = $this->applyLateEvidence($ledger, $now, $hours);

        $report['expired'] = $this->db->table(T::PERIODS)->where('state', self::PROVISIONAL)->where('period_start', '<', $from - 86400)
            ->update(['state' => self::INCOMPLETE, 'reasons' => '["expired"]', 'finalized_at' => $now, 'updated_at' => $now]);

        $this->finalizeDays($dayFrom, $now, $hours, $report);

        // Acknowledge the drop ledger up to just before the earliest hour that is still provisional.
        $ackMinute = ($earliestProvisional ?? ($lastHour + 3600)) / 60 - 1;
        if ($ackMinute > 0 && $ackMinute > $ledger->acknowledgedMinute()) {
            $ledger->acknowledge((int) $ackMinute);
            $report['acknowledged_minute'] = (int) $ackMinute;
        }

        return $report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hours
     * @param  array{hours_final: int, hours_incomplete: int, hours_rechecked_incomplete: int, late_evidence_hours: int, days_final: int, days_incomplete: int, expired: int, acknowledged_minute: ?int}  $report
     */
    private function finalizeDays(int $dayFrom, int $now, array $hours, array &$report): void
    {
        $lastDay = intdiv($now - $this->config->dayGraceSeconds, 86400) * 86400 - 86400;
        $days = [];
        foreach ($this->db->table(T::PERIODS)->where('granularity', 'day')->where('period_start', '>=', $dayFrom)->get() as $row) {
            $days[(int) $row->period_start] = (array) $row;
        }

        for ($d = $dayFrom; $d <= $lastDay; $d += 86400) {
            $row = $days[$d] ?? self::blankRow('day', $d, $now);
            $states = [];
            $dropped = 0;
            $covered = 0;
            for ($h = $d; $h < $d + 86400; $h += 3600) {
                $states[] = $hours[$h]['state'] ?? 'missing';
                $dropped += (int) ($hours[$h]['dropped_events'] ?? 0);
                $covered += (int) ($hours[$h]['coverage_minutes'] ?? 0);
            }
            $anyIncomplete = in_array(self::INCOMPLETE, $states, true) || in_array('missing', $states, true);

            if (in_array($row['state'], [self::FINAL, self::CORRECTED], true)) {
                if ($anyIncomplete) {   // an hour turned incomplete after the day was finalized
                    $row['state'] = self::INCOMPLETE;
                    $row['reasons'] = self::addReason((string) $row['reasons'], 'hours_incomplete');
                    $row['dropped_events'] = $dropped;
                    $row['updated_at'] = $now;
                    $this->save($row);
                    $report['days_incomplete']++;
                }

                continue;
            }
            if ($row['state'] === self::INCOMPLETE) {
                continue;
            }

            if (in_array(self::PROVISIONAL, $states, true) && $now < $d + 86400 + $this->config->maxWaitHours * 3600) {
                continue;
            }

            $reasons = [];
            if (in_array('missing', $states, true)) {
                $reasons[] = 'coverage_gap';
            }
            if (in_array(self::INCOMPLETE, $states, true) || in_array(self::PROVISIONAL, $states, true)) {
                $reasons[] = 'hours_incomplete';
            }
            $row['state'] = $reasons === [] ? self::FINAL : self::INCOMPLETE;
            $row['dropped_events'] = $dropped;
            $row['coverage_minutes'] = $covered;
            $row['reasons'] = (string) json_encode($reasons);
            $row['finalized_at'] = $now;
            $row['updated_at'] = $now;
            $this->save($row);
            $report[$row['state'] === self::FINAL ? 'days_final' : 'days_incomplete']++;
        }
    }

    /**
     * LATE EVIDENCE beyond the 24-hour re-check (Phase 2.1). The drop ledger flags any drop recorded for an
     * already-acknowledged minute, or for a minute whose slot already held a newer minute. Every period of any age
     * overlapping [late_min_minute, late_max_minute] that is final or corrected becomes incomplete
     * (`late_drop_evidence`) — never silently kept final — and its day with it. Conservative: the whole flagged range
     * is reopened, even if the drop belonged to only one of its minutes. Flags are cleared only after handling.
     *
     * @param  array<int, array<array-key, mixed>>  $hours
     */
    private function applyLateEvidence(DropLedger $ledger, int $now, array &$hours): int
    {
        $late = $ledger->lateEvidence();
        if ($late['min_minute'] === 0) {
            return 0;
        }

        $fromHour = intdiv($late['min_minute'] * 60, 3600) * 3600;
        $toHour = intdiv($late['max_minute'] * 60, 3600) * 3600;
        $reopened = 0;
        foreach ($this->db->table(T::PERIODS)->where('granularity', 'hour')->whereBetween('period_start', [$fromHour, $toHour])
            ->whereIn('state', [self::FINAL, self::CORRECTED])->get() as $row) {
            $row = (array) $row;
            $row['state'] = self::INCOMPLETE;
            $row['reasons'] = self::addReason((string) $row['reasons'], 'late_drop_evidence');
            $row['updated_at'] = $now;
            $this->save($row);
            $hours[(int) $row['period_start']] = $row;
            $reopened++;
        }
        $this->db->table(T::PERIODS)->where('granularity', 'day')
            ->whereBetween('period_start', [intdiv($fromHour, 86400) * 86400, intdiv($toHour, 86400) * 86400])
            ->whereIn('state', [self::FINAL, self::CORRECTED])
            ->update(['state' => self::INCOMPLETE, 'reasons' => '["hours_incomplete","late_drop_evidence"]', 'updated_at' => $now]);

        $ledger->clearLate($late['shards']);

        return $reopened;
    }

    /** @param array{dropped_events: int, uncertain_minutes: int, unreadable_shards: int, reboot_overlap: bool, wrapped: bool} $e */
    private static function uncertain(array $e): bool
    {
        return $e['uncertain_minutes'] > 0 || $e['unreadable_shards'] > 0 || $e['reboot_overlap'];
    }

    /** @return array<string, mixed> */
    private static function blankRow(string $granularity, int $start, int $now): array
    {
        return ['granularity' => $granularity, 'period_start' => $start, 'state' => self::PROVISIONAL, 'events' => 0, 'batches' => 0,
            'malformed' => 0, 'late_batches' => 0, 'coverage_minutes' => 0, 'dropped_events' => 0, 'reasons' => '[]',
            'finalized_at' => null, 'corrected_at' => null, 'updated_at' => $now];
    }

    /** @param array<string, mixed> $row */
    private function save(array $row): void
    {
        $this->db->table(T::PERIODS)->upsert([$row], ['granularity', 'period_start'],
            ['state', 'coverage_minutes', 'dropped_events', 'reasons', 'finalized_at', 'updated_at']);
    }

    /**
     * Minutes that still have spool files waiting (active or sealed) or quarantined.
     *
     * @return array{pending: array<int, true>, quarantine: array<int, true>}
     */
    private function spoolFilesByMinute(): array
    {
        $out = ['pending' => [], 'quarantine' => []];
        foreach (['pending' => [$this->spool->activeDir(), $this->spool->sealedDir()], 'quarantine' => [$this->spool->quarantineDir()]] as $kind => $dirs) {
            foreach ($dirs as $dir) {
                foreach (@scandir($dir) ?: [] as $name) {
                    if (! str_ends_with($name, '.ndjson')) {
                        continue;
                    }
                    $minute = SpoolConfig::minuteFromKey(substr($name, 0, 12));
                    if ($minute !== null) {
                        $out[$kind][$minute] = true;
                    }
                }
            }
        }

        return $out;
    }
}
