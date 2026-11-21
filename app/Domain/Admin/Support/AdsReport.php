<?php

namespace App\Domain\Admin\Support;

use Illuminate\Support\Facades\DB;

/**
 * F01 / B6a — OLD `view_report()` / `draw_ads_chart()` (`modules/ads/admin/index.php:968-1300`).
 *
 * OLD drew, for ONE position and a date range (optionally one type), every ENABLED ad whose period overlaps
 * the range, laid out in rows «المجموعة N» of non-overlapping ads, each bar titled with name, start, end,
 * type and percentage and linking to the edit page. Its grouping code (`ads_availability()`) was order-
 * dependent and printed debug arrays into the page.
 *
 * Same data, secure and deterministic: the same overlap rule —
 *   `show = 1 AND position = ? [AND type = ?] AND (startdate BETWEEN from AND to
 *    OR enddate BETWEEN from AND to OR (startdate < from AND enddate > to))`
 * — with lanes assigned greedily by start date (an ad joins the first lane whose last ad ended before it
 * starts), plus OLD's `get_sum_count_ads()` summary (count, SUM(percentage)) over the listed ads.
 * Rendered as a table; OLD's chart markup and colours are not reproduced.
 */
final class AdsReport
{
    /**
     * @return array{rows: list<array<string, mixed>>, count: int, percentage_sum: int, lanes: int}
     */
    public static function build(int $position, string $from, string $to, ?int $type = null): array
    {
        $query = DB::connection('main')->table('nuke_ads')
            ->where('show', 1)
            ->where('position', $position)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('startdate', [$from, $to])
                    ->orWhereBetween('enddate', [$from, $to])
                    ->orWhere(fn ($q) => $q->where('startdate', '<', $from)->where('enddate', '>', $to));
            })
            ->orderBy('startdate')->orderBy('id');

        if ($type !== null) {
            $query->where('type', $type);
        }

        $laneEnds = [];
        $rows = [];
        foreach ($query->get(['id', 'name', 'startdate', 'enddate', 'type', 'percentage']) as $ad) {
            $start = (string) $ad->startdate;
            $end = (string) $ad->enddate;

            $lane = null;
            foreach ($laneEnds as $index => $laneEnd) {
                if ($laneEnd < $start) {
                    $lane = $index;
                    break;
                }
            }
            if ($lane === null) {
                $lane = count($laneEnds);
            }
            $laneEnds[$lane] = $end;

            $rows[] = [
                'id' => (int) $ad->id,
                'name' => (string) $ad->name,
                'startdate' => $start,
                'enddate' => $end,
                'type' => (int) $ad->type,
                'percentage' => (int) $ad->percentage,
                'lane' => $lane + 1,
                'days_in_range' => self::daysInRange($start, $end, $from, $to),
            ];
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
            'percentage_sum' => array_sum(array_column($rows, 'percentage')),
            'lanes' => count($laneEnds),
        ];
    }

    /** Inclusive day count of the overlap between [start, end] and [from, to]. */
    private static function daysInRange(string $start, string $end, string $from, string $to): int
    {
        $a = strtotime(max($start, $from));
        $b = strtotime(min($end, $to));

        return $a === false || $b === false || $b < $a ? 0 : intdiv($b - $a, 86400) + 1;
    }
}
