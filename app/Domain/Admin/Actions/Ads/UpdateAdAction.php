<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\LegacyAds;
use App\Domain\Content\Models\HomeAd;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6a — OLD `edit_ads()` (`modules/ads/admin/index.php:414-570`). Writes only the columns OLD's edit
 * wrote, and only when they change; an unchanged submission writes nothing and records nothing.
 */
final class UpdateAdAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly StoreAdImageAction $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated AdRequest input
     * @return bool whether anything changed
     */
    public function execute(AdminUser $actor, HomeAd $ad, array $data, ?UploadedFile $image, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, LegacyAds::PERMISSION, $ad);
        // Editing an existing code ad, or turning an image ad into one, is super-admin only.
        AdRecord::authorizeCode($actor, (int) $ad->type, (int) $data['type']);

        $uploadedUrl = (int) $data['type'] === LegacyAds::TYPE_IMAGE
            && (int) $data['path_type'] === LegacyAds::PATH_UPLOAD
            && $image !== null
                ? $this->images->execute($image)
                : null;

        return DB::connection('main')->transaction(function () use ($actor, $ad, $data, $uploadedUrl, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_ads')->where('id', $ad->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);
            AdRecord::authorizeCode($actor, (int) $row->type, (int) $data['type']);   // re-checked on the locked row

            $changes = [];
            foreach (AdRecord::valuesForUpdate($data, $uploadedUrl, $row) as $column => $value) {
                if ((string) ($row->{$column} ?? '') !== (string) $value) {
                    $changes[$column] = $value;
                }
            }

            if ($changes === []) {
                return false;
            }

            $main->table('nuke_ads')->where('id', $row->id)->update($changes);
            $after = $main->table('nuke_ads')->where('id', $row->id)->first();

            $before = array_intersect_key(AdRecord::snapshot($row), $changes);
            $now = array_intersect_key(AdRecord::snapshot($after), $changes);
            // The code fingerprint depends on the type; keep it in both halves when either side is a code ad.
            if (isset($changes['type']) && ! isset($changes['image_path'])) {
                $before['image_path'] = AdRecord::snapshot($row)['image_path'];
                $now['image_path'] = AdRecord::snapshot($after)['image_path'];
            }

            $this->audit->record($actor, 'edit_ad', 'ads', 'ad', (int) $row->id, ['before' => $before, 'after' => $now], $ip);

            return true;
        });
    }
}
