<?php

namespace App\Domain\Content\Ads;

/**
 * F01 / B6c-1 — OLD's public eligibility rule, verbatim (`old-project/way2allah_functions.php:423`
 * `show_ads_byposition()`; identical in Gen 2 `7amlat/home_functions.php:437`).
 *
 * - The candidate pool is `position = ? AND show = 1` (the query); this class decides each candidate.
 * - `ads_show_type = 'period'` (exact, case-sensitive): shown when `today > startdate AND today <= enddate`,
 *   compared as Y-m-d strings. **The start day is excluded and the end day included** (plan decision P2 is still
 *   open; parity kept). A NULL date compares as '' (OLD/PHP string semantics), so a period ad with no end date is
 *   never shown, and one with no start date is shown up to its end date.
 * - Any other `ads_show_type` (OLD's `numview`): shown while `num_view <= required_num_view`.
 *
 * `percentage` (the share) is ignored, as OLD's public renderer ignored it.
 */
final class LegacyAdEligibility
{
    public static function isEligible(object $ad, string $today): bool
    {
        if ((string) ($ad->ads_show_type ?? '') === 'period') {
            $start = (string) ($ad->startdate ?? '');
            $end = (string) ($ad->enddate ?? '');

            return strcmp($today, $start) > 0 && strcmp($today, $end) <= 0;
        }

        return (int) ($ad->num_view ?? 0) <= (int) ($ad->required_num_view ?? 0);
    }
}
