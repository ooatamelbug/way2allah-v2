<?php

namespace App\Domain\Content\Services\Mobile;

use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — the rating summary of a material, as OLD
 * `modules/Mobile/voteinclude.php` computed it from the vote rows:
 *
 * - registered votes, anonymous votes (`ratinguser` = the site's anonymous
 *   name) and "outside" votes are averaged separately, then weighted:
 *   anonymous 1/10, outside 1/20 of a registered vote (`$anonweight = 10`,
 *   `$outsideweight = 20`); a single kind of vote is a plain average;
 * - `totalvotes` = number of vote rows; `totalcomments` = rows with a
 *   non-empty comment.
 *
 * The summary is stored truncated to a whole number, as OLD `addrating`
 * stored it (`intval($finalrating)`) — every stored value is whole. OLD's
 * admin vote deletion stored the unrounded value instead; one rule is used
 * for every path here (plan §26.7).
 *
 * Recomputing replaces the stored counters with what the vote rows say —
 * OLD's own contract. Historical vote rows are gone (the table is empty),
 * so the first new vote on a material replaces its historical counters.
 */
final class MobileRating
{
    /** @return array{downloadratingsummary: int, totalvotes: int, totalcomments: int} */
    public static function recompute(int $lid): array
    {
        $votes = DB::connection('main')->table('nuke_downloads_votedata')->where('ratinglid', $lid)
            ->get(['rating', 'ratinguser', 'ratingcomments']);
        $summary = self::summarize($votes->all(), MobileSite::anonymousName());

        DB::connection('main')->table('nuke_downloads_downloads')->where('lid', $lid)->update($summary);

        return $summary;
    }

    /**
     * @param  list<object>  $votes
     * @return array{downloadratingsummary: int, totalvotes: int, totalcomments: int}
     */
    public static function summarize(array $votes, string $anonymous): array
    {
        $total = count($votes);
        $comments = 0;
        $anon = $anonValue = $outside = $outsideValue = $regValue = 0;

        foreach ($votes as $vote) {
            $rating = (int) $vote->rating;
            if (trim((string) $vote->ratingcomments) !== '') {
                $comments++;
            }
            if ($vote->ratinguser === $anonymous) {
                $anon++;
                $anonValue += $rating;
            } elseif ($vote->ratinguser === 'outside') {
                $outside++;
                $outsideValue += $rating;
            } else {
                $regValue += $rating;
            }
        }
        $reg = $total - $anon - $outside;

        if ($total === 0) {
            $final = 0.0;
        } elseif ($anon === 0 && $reg === 0) {
            $final = $outsideValue / $outside;
        } elseif ($outside === 0 && $reg === 0) {
            $final = $anonValue / $anon;
        } elseif ($outside === 0 && $anon === 0) {
            $final = $regValue / $reg;
        } elseif ($reg === 0) {
            $anonWeight = (int) config('mobile_downloads.anonymous_weight');
            $outsideWeight = (int) config('mobile_downloads.outside_weight');
            $avgAnon = $anonValue / $anon;
            $avgOutside = $outsideValue / $outside;
            if ($anonWeight > $outsideWeight) {
                $impactAnon = $anon;
                $impactOutside = $outside / ($anonWeight / $outsideWeight);
            } else {
                $impactOutside = $outside;
                $impactAnon = $anon / ($outsideWeight / $anonWeight);
            }
            $final = (($avgOutside * $impactOutside) + ($avgAnon * $impactAnon)) / ($impactAnon + $impactOutside);
        } else {
            $impactReg = $reg;
            $impactAnon = $anon / (int) config('mobile_downloads.anonymous_weight');
            $impactOutside = $outside / (int) config('mobile_downloads.outside_weight');
            $avgReg = $regValue / $reg;
            $avgAnon = $anon > 0 ? $anonValue / $anon : 0;
            $avgOutside = $outside > 0 ? $outsideValue / $outside : 0;
            $final = (($avgReg * $impactReg) + ($avgAnon * $impactAnon) + ($avgOutside * $impactOutside)) / ($impactReg + $impactAnon + $impactOutside);
        }

        return ['downloadratingsummary' => (int) $final, 'totalvotes' => $total, 'totalcomments' => $comments];
    }
}
