<?php

namespace App\Support\Downloads\Monitoring;

use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Database\Connection;

/**
 * READ-ONLY growth report for `download_rejections` (Phase 3C). The table is operational and audit evidence: nothing
 * here deletes, exports or rewrites a row, and the table is not part of any prune (owner decision, 2026-11-11).
 *
 * Bounded queries only, every one on `idx_dr_created`:
 *  - oldest / newest: MIN / MAX(created_at) — answered from the index ends;
 *  - growth: rows per UTC day for the last N days (≤ 90) — a covering range scan of that window only;
 *  - total: on MySQL/MariaDB the table statistics' estimate (information_schema, no scan) unless `exact` is asked for,
 *    which is a COUNT(*) over the whole index (cost grows with the table — run it off-peak); on SQLite always exact;
 *  - storage: data_length + index_length from information_schema (MySQL/MariaDB), and bytes per row derived from it.
 */
final class RejectionLogReport
{
    public function __construct(private readonly Connection $db) {}

    /** @return array<string, mixed> */
    public function collect(int $now, int $days = 30, bool $exact = false): array
    {
        $days = max(1, min(90, $days));
        $table = DownloadRejectionRecorder::TABLE;
        $mysql = in_array($this->db->getDriverName(), ['mysql', 'mariadb'], true);

        $bounds = $this->db->selectOne("SELECT MIN(created_at) AS oldest, MAX(created_at) AS newest FROM {$table}");
        $from = (intdiv($now, 86400) - $days + 1) * 86400;
        $dayExpr = $mysql ? 'created_at DIV 86400' : 'created_at / 86400';
        $perDay = [];
        foreach ($this->db->select("SELECT {$dayExpr} AS d, COUNT(*) AS n FROM {$table} WHERE created_at >= ? GROUP BY {$dayExpr} ORDER BY d", [$from]) as $row) {
            $perDay[gmdate('Y-m-d', (int) $row->d * 86400)] = (int) $row->n;
        }
        $series = [];
        for ($d = $from; $d <= $now; $d += 86400) {
            $series[gmdate('Y-m-d', $d)] = $perDay[gmdate('Y-m-d', $d)] ?? 0;
        }

        $stats = null;
        if ($mysql) {
            $stats = $this->db->selectOne('SELECT table_rows, data_length, index_length, avg_row_length FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
        }
        $total = ($exact || ! $mysql) ? (int) $this->db->selectOne("SELECT COUNT(*) AS c FROM {$table}")->c : null;
        $estimate = $stats === null ? null : (int) $stats->table_rows;
        $bytes = $stats === null ? null : (int) $stats->data_length + (int) $stats->index_length;
        $rowsForRatio = $total ?? $estimate;

        $inWindow = array_sum($series);
        $last7 = array_sum(array_slice($series, -7));

        return [
            'table' => $table,
            'total_rows' => $total,
            'total_rows_estimate' => $estimate,
            'total_is_exact' => $total !== null,
            'oldest' => $bounds?->oldest === null ? null : gmdate('c', (int) $bounds->oldest),
            'newest' => $bounds?->newest === null ? null : gmdate('c', (int) $bounds->newest),
            'window_days' => $days,
            'per_day' => $series,
            'rows_in_window' => $inWindow,
            'rows_last_7_days' => $last7,
            'average_per_day' => round($inWindow / $days, 1),
            'busiest_day' => $inWindow === 0 ? null : array_search(max($series), $series, true),
            'storage_bytes' => $bytes,
            'bytes_per_row' => $bytes !== null && $rowsForRatio ? (int) round($bytes / $rowsForRatio) : null,
            'projected_rows_per_year' => (int) round($inWindow / $days * 365),
            'projected_bytes_per_year' => $bytes !== null && $rowsForRatio ? (int) round($inWindow / $days * 365 * $bytes / $rowsForRatio) : null,
        ];
    }
}
