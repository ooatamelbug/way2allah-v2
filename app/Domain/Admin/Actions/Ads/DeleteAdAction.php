<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\LegacyAds;
use App\Domain\Content\Models\HomeAd;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6a — OLD `del_ads()` (`modules/ads/admin/index.php:573-596`): a hard DELETE, kept. The full row goes
 * to the audit log first (code ads as a fingerprint), so a deletion can be reconstructed by hand. The image
 * file, if any, is left in `media/bann/` — OLD never removed it either.
 *
 * Deleting a raw HTML/JS ad (type 0) is super-admin only (owner, B6a review), like every other change to one.
 */
final class DeleteAdAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, HomeAd $ad, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, LegacyAds::PERMISSION, $ad);
        AdRecord::authorizeCode($actor, (int) $ad->type);

        DB::connection('main')->transaction(function () use ($actor, $ad, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_ads')->where('id', $ad->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);
            AdRecord::authorizeCode($actor, (int) $row->type);   // re-checked on the locked row

            $main->table('nuke_ads')->where('id', $row->id)->delete();

            $this->audit->record($actor, 'del_ad', 'ads', 'ad', (int) $row->id, ['deleted' => AdRecord::snapshot($row)], $ip);
        });
    }
}
