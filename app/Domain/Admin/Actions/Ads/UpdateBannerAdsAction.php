<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F01 / B6b — OLD `w2a_banners_ads_main($items_ids, 1)` (`modules/banners_ads/admin/index.php`): the admin's
 * saved list becomes the set of Khotab items whose banners the home banner strip may show (`nuke_banners_ads`).
 *
 * OLD ran `DELETE FROM nuke_banners_ads` and re-inserted every id (new ids, new `creation_date`, SQL-injectable,
 * and an empty list fatally inserted `media_id=`). Same resulting set, non-destructive: inside one transaction only
 * the removed rows are deleted and only the new items inserted, so unchanged rows keep their `id` and
 * `creation_date`.
 *
 * - `keep` = members of the CURRENT set the admin left ticked; anything else is rejected (tampering).
 * - `add`  = Khotab items with `banner = 1` (OLD offered only those); anything else is rejected.
 *   An existing member that no longer has a banner may still be KEPT — stored data is not silently dropped.
 * - `setVersion` = {@see self::fingerprint()} of the set the form was rendered from. Removal is inferred from
 *   absence (OLD's replacement semantics), so a stale form would silently undo another admin's concurrent
 *   change; a mismatch under the row lock is therefore rejected and nothing is written (owner review B6b §2).
 */
final class UpdateBannerAdsAction
{
    public const PERMISSION = 'banners_ads.managebanners';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>  $keep
     * @param  list<int>  $add
     * @return array{added: list<int>, removed: list<int>}
     */
    public function execute(AdminUser $actor, array $keep, array $add, string $setVersion, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, self::PERMISSION);

        return DB::connection('main')->transaction(function () use ($actor, $keep, $add, $setVersion, $ip) {
            $main = DB::connection('main');
            $current = $main->table('nuke_banners_ads')->lockForUpdate()->pluck('media_id')
                ->map(fn ($id) => (int) $id)->unique()->values()->all();

            $keep = array_values(array_unique(array_map('intval', $keep)));
            $add = array_values(array_unique(array_map('intval', $add)));

            if (! hash_equals(self::fingerprint($current), $setVersion) || array_diff($keep, $current) !== []) {
                throw ValidationException::withMessages(['keep' => 'قائمة المواد المختارة تغيرت، من فضلك أعد تحميل الصفحة']);
            }

            $newIds = array_values(array_diff($add, $current));
            if ($newIds !== []) {
                $eligible = $main->table('nuke_islamic_khotab')->whereIn('id', $newIds)->where('banner', 1)->pluck('id')
                    ->map(fn ($id) => (int) $id)->all();
                if (array_diff($newIds, $eligible) !== []) {
                    throw ValidationException::withMessages(['add' => 'لا يمكن إضافة مادة لا تحتوي على بنر']);
                }
            }

            $target = array_values(array_unique([...$keep, ...$add]));
            $removed = array_values(array_diff($current, $target));
            sort($newIds);
            sort($removed);

            if ($newIds === [] && $removed === []) {
                return ['added' => [], 'removed' => []];
            }

            if ($removed !== []) {
                $main->table('nuke_banners_ads')->whereIn('media_id', $removed)->delete();
            }
            $now = now()->format('Y-m-d H:i:s');
            foreach ($newIds as $mediaId) {
                $main->table('nuke_banners_ads')->insert(['media_id' => $mediaId, 'creation_date' => $now]);
            }

            // One logical record (the set): entity id 1 by convention.
            $this->audit->record($actor, 'update_banners', 'banners_ads', 'banner_set', 1, [
                'added' => $newIds,
                'removed' => $removed,
                'count_after' => count($target),
            ], $ip);

            return ['added' => $newIds, 'removed' => $removed];
        });
    }

    /**
     * Order- and duplicate-insensitive fingerprint of a set of `media_id`s (rendered into the form, re-checked on save).
     *
     * @param  iterable<int|string>  $mediaIds
     */
    public static function fingerprint(iterable $mediaIds): string
    {
        $ids = [];
        foreach ($mediaIds as $id) {
            $ids[(int) $id] = (int) $id;
        }
        sort($ids);

        return hash('sha256', implode(',', $ids));
    }
}
