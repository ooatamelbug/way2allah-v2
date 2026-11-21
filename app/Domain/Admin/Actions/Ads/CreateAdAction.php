<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\LegacyAds;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** F01 / B6a — OLD `add_ads()` (`modules/ads/admin/index.php:324-408`). */
final class CreateAdAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly StoreAdImageAction $images,
    ) {}

    /** @param  array<string, mixed>  $data  validated AdRequest input */
    public function execute(AdminUser $actor, array $data, ?UploadedFile $image, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, LegacyAds::PERMISSION);
        AdRecord::authorizeCode($actor, (int) $data['type']);

        $uploadedUrl = (int) $data['type'] === LegacyAds::TYPE_IMAGE
            && (int) $data['path_type'] === LegacyAds::PATH_UPLOAD
            && $image !== null
                ? $this->images->execute($image)
                : null;

        $values = AdRecord::valuesForCreate($data, $uploadedUrl);

        return DB::connection('main')->transaction(function () use ($actor, $values, $ip) {
            $main = DB::connection('main');
            $id = (int) $main->table('nuke_ads')->insertGetId($values);
            $row = $main->table('nuke_ads')->where('id', $id)->first();

            $this->audit->record($actor, 'add_ad', 'ads', 'ad', $id, ['created' => AdRecord::snapshot($row)], $ip);

            return $id;
        });
    }
}
