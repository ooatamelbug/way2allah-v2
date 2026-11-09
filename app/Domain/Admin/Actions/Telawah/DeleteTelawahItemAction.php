<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Support\Facades\DB;

/**
 * «مسح» on the item page — OLD `TelawahDeleteTelawah` → `ConfirmDeleteTelawah`
 * (`modules/Telawah/admin/index.php`:401-433, production-hash-verified): OLD
 * deleted the item row and ran `telawah - 1` on its group — nothing else.
 *
 * **Owner-approved safety correction (2026-10-29):** OLD's delete left the
 * item's own data behind. Laravel removes, in one transaction, exactly the
 * data that belongs to this item and to nothing else:
 *
 * - its mirrors (`nuke_telawah_mirror.telawa_id = id`);
 * - its comments, every state (`nuke_telawah_comments.khid = id`);
 * - its technical row (`nuke_telawah_advanced.id = id`). OLD's only writer
 *   (`backup.php`, cat 3) keys it by the ITEM id, and the item page reads it
 *   by item id; Telawah mirrors have no backup category, so no other id
 *   space shares the table;
 * - the item row, and `telawah - 1` on its group (floored at 0; OLD was not).
 *
 * Not touched: groups and the sura list (shared references); the audit /
 * legacy logs; `nuke_backup_booking` (a transient backup-worker queue, not
 * item data); `nuke_brokenlinks` (written only by Khotab, `divid = 1` — the
 * Telawah item page's read of it is a copy of Khotab code and collides with
 * Khotab ids, so none of its rows are Telawah's).
 */
final class DeleteTelawahItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{group_id: int, title: string} */
    public function execute(AdminUser $actor, TelawahItem $item, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'telawah.deltelawah', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $main = DB::connection('main');
            $itemId = (int) $item->id;
            $row = $main->table('nuke_telawah_telawah')->where('id', $itemId)->lockForUpdate()->first(['id', 'title', 'group_id']);
            abort_if($row === null, 404);

            $mirrorIds = $main->table('nuke_telawah_mirror')->where('telawa_id', $itemId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $comments = $main->table('nuke_telawah_comments')->where('khid', $itemId)->count();

            $main->table('nuke_telawah_mirror')->where('telawa_id', $itemId)->delete();
            $main->table('nuke_telawah_comments')->where('khid', $itemId)->delete();
            $advanced = $main->table('nuke_telawah_advanced')->where('id', $itemId)->delete();
            $main->table('nuke_telawah_telawah')->where('id', $itemId)->delete();

            $groupId = (int) $row->group_id;
            if ($groupId > 0) {
                $main->table('nuke_telawah_groups')->where('id', $groupId)->where('telawah', '>', 0)->decrement('telawah');
            }

            $this->audit->record($actor, 'del_item', 'telawah', 'item', $itemId, [
                'group_id' => $groupId,
                'mirrors_deleted' => $mirrorIds,
                'comments_deleted' => $comments,
                'advanced_deleted' => $advanced,
            ], $ip);

            return ['group_id' => $groupId, 'title' => (string) $row->title];
        });
    }
}
