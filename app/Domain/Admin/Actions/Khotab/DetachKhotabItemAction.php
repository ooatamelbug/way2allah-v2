<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * The icon beside each item on a group or series page — OLD
 * `KhotabUpKhotab` → `UpKhotab` (`modules/Khotab/admin/index.php:1496-1528`,
 * production working copy): take the item out of its series, or, when it has
 * none, out of its group.
 *
 * OLD's order of writes, reproduced exactly:
 *
 * - in a series: `update_series_location(series, location)` runs FIRST
 *   (so it still counts this item), then the series `count - 1`, then the
 *   item's `ser_id = 0`;
 * - else in a group: the group `count - 1`, the item's `group_id = 0`, then
 *   `update_group_location(group, location)` (now without this item);
 * - else: nothing changes (OLD printed an error).
 *
 * The message is OLD's own («تم نقل المادة '' … '' خارج …»).
 *
 * Authorization: OLD checked nothing (any administrator). Here it is
 * `khotab.moveitem` («نقل مادة»), the menu key for moving an item. OLD sent
 * an AJAX GET to `https://way2allah.com/admin.php`; here it is a same-site,
 * CSRF-protected POST, transactional and audited.
 */
final class DetachKhotabItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
    ) {}

    /** @return array{detached: bool, from: string, message: string, group_id: int, ser_id: int} */
    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.moveitem', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()
                ->first(['id', 'title', 'ser_id', 'group_id', 'location_id']);
            abort_if($row === null, 404);

            $serId = (int) $row->ser_id;
            $groupId = (int) $row->group_id;
            $locationId = (int) $row->location_id;
            $title = (string) $row->title;

            if ($serId > 0) {
                $this->locations->update('series', $serId, $locationId);
                $main->table('nuke_islamic_series')->where('id', $serId)->update(['count' => DB::raw('count-1')]);
                $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['ser_id' => 0]);
                $from = 'series';
                $message = "تم نقل المادة '' {$title} '' خارج السلسلة الحالية";
            } elseif ($groupId > 0) {
                $main->table('nuke_islamic_groups')->where('id', $groupId)->update(['count' => DB::raw('count-1')]);
                $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['group_id' => 0]);
                $this->locations->update('group', $groupId, $locationId);
                $from = 'group';
                $message = "تم نقل المادة '' {$title} '' خارج المجموعة الحالية";
            } else {
                return ['detached' => false, 'from' => '', 'message' => 'حدث خطأ في نقل المادة ! برجاء مراجعة الإدارة الفنية', 'group_id' => 0, 'ser_id' => 0];
            }

            $this->audit->record($actor, 'detach_item', 'khotab', 'item', (int) $row->id, [
                'from' => $from,
                'ser_id_before' => $serId,
                'group_id_before' => $groupId,
                'location_id' => $locationId,
            ], $ip);

            return ['detached' => true, 'from' => $from, 'message' => $message, 'group_id' => $groupId, 'ser_id' => $serId];
        });
    }
}
