<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use Illuminate\Database\Connection;

/**
 * Growth monitoring for download_ip_ua_hourly (Phase 2.2) — READ-ONLY. It never deletes, caps or merges rows: the
 * exact per-hour IP/User-Agent counts stay exact; abnormal growth is only reported.
 *
 * Bounded queries: one count per hour, each limited to (critical threshold × 2) rows via a LIMIT subquery on the
 * primary-key prefix (`hour`), so a bot storm cannot make monitoring itself expensive; a capped count is reported as
 * "at least". Storage is projected as average rows/hour × 24 × retention days × average row size (configurable;
 * measured on MariaDB at 0.6–1.5 KB). On MySQL/MariaDB the table's actual size comes from information_schema.
 *
 * Warnings: `rows_per_hour_warning` / `rows_per_hour_critical` (latest full hour), `abnormal_growth` (latest full hour
 * ≥ growth ratio × median of the earlier hours, and at least the warning threshold ÷ 10), `projected_storage`.
 */
final class IpUaGrowthMonitor
{
    public function __construct(private readonly Connection $db, private readonly RollupConfig $config) {}

    /** @return array<string, mixed> */
    public function report(int $now, int $hours = 24): array
    {
        $hours = max(2, min(168, $hours));
        $cap = max($this->config->ipUaCriticalRowsPerHour, $this->config->ipUaWarnRowsPerHour) * 2;
        $current = intdiv($now, 3600) * 3600;
        $perHour = [];
        for ($h = $current - $hours * 3600; $h <= $current; $h += 3600) {
            $count = (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM (SELECT 1 FROM '.T::IP_UA_HOURLY.' WHERE hour = ? LIMIT ?) AS bounded', [$h, $cap])->c;
            $perHour[$h] = ['rows' => $count, 'at_least' => $count >= $cap];
        }

        $latestFull = $perHour[$current - 3600]['rows'];
        $earlier = array_column(array_slice($perHour, 0, -2, true), 'rows');
        sort($earlier);
        $median = $earlier === [] ? 0 : $earlier[intdiv(count($earlier), 2)];
        $average = intdiv(array_sum(array_column($perHour, 'rows')), count($perHour));
        $projected = $average * 24 * $this->config->retentionIpUaDays * $this->config->ipUaAvgRowBytes;

        $actualBytes = null;
        if (in_array($this->db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $row = $this->db->selectOne('SELECT data_length + index_length AS bytes FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [T::IP_UA_HOURLY]);
            $actualBytes = $row === null ? null : (int) $row->bytes;
        }

        $warnings = [];
        if ($latestFull >= $this->config->ipUaCriticalRowsPerHour) {
            $warnings[] = 'rows_per_hour_critical';
        } elseif ($latestFull >= $this->config->ipUaWarnRowsPerHour) {
            $warnings[] = 'rows_per_hour_warning';
        }
        if ($median > 0 && $latestFull >= $median * $this->config->ipUaGrowthRatio && $latestFull >= intdiv($this->config->ipUaWarnRowsPerHour, 10)) {
            $warnings[] = 'abnormal_growth';
        }
        if ($projected >= $this->config->ipUaProjectedWarnBytes) {
            $warnings[] = 'projected_storage';
        }

        return [
            'definition' => 'rows = IP/User-Agent combinations per hour (not people)',
            'hours' => array_map(fn (int $h, array $v) => ['hour' => gmdate('Y-m-d H:00', $h)] + $v, array_keys($perHour), $perHour),
            'latest_full_hour_rows' => $latestFull,
            'median_earlier_hours' => $median,
            'average_rows_per_hour' => $average,
            'projected_bytes_at_retention' => $projected,
            'retention_days' => $this->config->retentionIpUaDays,
            'actual_table_bytes' => $actualBytes,
            'thresholds' => ['warn_rows_per_hour' => $this->config->ipUaWarnRowsPerHour, 'critical_rows_per_hour' => $this->config->ipUaCriticalRowsPerHour,
                'growth_ratio' => $this->config->ipUaGrowthRatio, 'projected_warn_bytes' => $this->config->ipUaProjectedWarnBytes],
            'warnings' => $warnings,
        ];
    }
}
