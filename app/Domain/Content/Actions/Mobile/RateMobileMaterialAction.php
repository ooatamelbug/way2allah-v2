<?php

namespace App\Domain\Content\Actions\Mobile;

use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Services\Mobile\MobileRating;
use App\Domain\Content\Services\Mobile\MobileSite;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — rate a material, optionally with a comment.
 * Replaces OLD `ratedownload` / `addrating` (`modules/Mobile/index.php:2145-2330`):
 *
 * - members rate under their username and may add a comment; anonymous
 *   visitors rate under the site's anonymous name, without a comment;
 * - refused when: a member rates a material they submitted
 *   (`postervote`); a member has already rated it (`regflood`); an
 *   anonymous visitor from the same IP already rated it within
 *   `$anonwaitdays` (1 day, `anonflood`);
 * - otherwise one `nuke_downloads_votedata` row (IP, timestamp), then the
 *   material's counters are recomputed from its votes (MobileRating).
 *
 * The material row is locked for the check-and-insert, so two concurrent
 * votes cannot both pass a once-only rule. OLD's "outside" (cross-site)
 * voting is not reproduced (plan §26.7).
 */
final class RateMobileMaterialAction
{
    public const RATED = 'rated';

    public const OWN_MATERIAL = 'own';

    public const ALREADY_RATED = 'already';

    public const ANONYMOUS_WAIT = 'anon_wait';

    public function execute(?VbUser $member, MobileMaterial $material, int $rating, string $comment, string $ip): string
    {
        return DB::connection('main')->transaction(function () use ($member, $material, $rating, $comment, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_downloads_downloads')->where('lid', $material->getKey())->lockForUpdate()->first(['lid', 'submitter']);
            abort_if($row === null, 404);

            $anonymous = MobileSite::anonymousName();
            $user = $member !== null ? (string) $member->username : $anonymous;
            $votes = $main->table('nuke_downloads_votedata')->where('ratinglid', $row->lid);

            if ($member !== null) {
                if ($row->submitter !== '' && $row->submitter === $user) {
                    return self::OWN_MATERIAL;
                }
                if ((clone $votes)->where('ratinguser', $user)->exists()) {
                    return self::ALREADY_RATED;
                }
            } else {
                $since = date('Y-m-d 00:00:00', time() - 86400 * ((int) config('mobile_downloads.anonymous_wait_days') - 1));
                if ((clone $votes)->where('ratinguser', $anonymous)->where('ratinghostname', $ip)->where('ratingtimestamp', '>=', $since)->exists()) {
                    return self::ANONYMOUS_WAIT;
                }
            }

            $main->table('nuke_downloads_votedata')->insert([
                'ratinglid' => (int) $row->lid,
                'ratinguser' => $user,
                'rating' => $rating,
                'ratinghostname' => mb_substr($ip, 0, 60),
                'ratingcomments' => $member !== null ? $comment : '',
                'ratingtimestamp' => date('Y-m-d H:i:s'),
            ]);
            MobileRating::recompute((int) $row->lid);

            return self::RATED;
        });
    }
}
