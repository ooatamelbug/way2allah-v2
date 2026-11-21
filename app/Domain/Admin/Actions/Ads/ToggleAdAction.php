<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\LegacyAds;
use App\Domain\Content\Models\HomeAd;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6a — OLD `update_show()` (`modules/ads/admin/index.php:597-622`) flipped `show` from a GET link
 * (`?op=update_show&ads_id=…&sh=…`, injectable, no CSRF). Here it is a POST with CSRF that sets an EXPLICIT
 * target state, so a repeated or stale click cannot flip it back. A code ad (type 0) is super-admin only.
 *
 * Note: `show` only marks the ad as enabled in the table. No public page renders advertisements (B6c is not
 * implemented, and its rendering switch defaults to off).
 */
final class ToggleAdAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return bool whether the state changed */
    public function execute(AdminUser $actor, HomeAd $ad, bool $show, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, LegacyAds::PERMISSION, $ad);
        AdRecord::authorizeCode($actor, (int) $ad->type);

        return DB::connection('main')->transaction(function () use ($actor, $ad, $show, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_ads')->where('id', $ad->getKey())->lockForUpdate()->first(['id', 'show', 'type']);
            abort_if($row === null, 404);
            AdRecord::authorizeCode($actor, (int) $row->type);   // re-checked on the locked row

            $target = $show ? 1 : 0;
            if ((int) $row->show === $target) {
                return false;
            }

            $main->table('nuke_ads')->where('id', $row->id)->update(['show' => $target]);

            $this->audit->record($actor, 'toggle_ad', 'ads', 'ad', (int) $row->id, [
                'before' => ['show' => $row->show === null ? null : (int) $row->show],
                'after' => ['show' => $target],
            ], $ip);

            return true;
        });
    }
}
