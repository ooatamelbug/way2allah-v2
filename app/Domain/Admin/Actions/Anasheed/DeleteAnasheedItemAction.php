<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Actions\Categories\CategoryCounter;
use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * «حذف المادة» — OLD `AnasheedDeleteAnasheed` → `ConfirmDeleteAnasheed`
 * (`modules/Anasheed/admin/index.php`, production working copy): OLD deleted
 * the item row and took 1 from its group's `anasheed` counter — nothing else.
 *
 * **Owner-approved safety correction (2026-10-23):** OLD's delete left the
 * item's own data behind. Laravel removes, in one transaction, exactly the
 * data that belongs to this item and to nothing else:
 *
 * - its mirrors (`nuke_anasheed_mirror.khid = id`);
 * - its comments, every state (`nuke_anasheed_comments.khid = id`);
 * - its technical row (`nuke_anasheed_advanced.id = id`). OLD's only writer
 *   of that table (`backup.php`, cat 4) keys it by the ITEM id; mirror
 *   metadata goes to `nuke_anasheed_advanced_m`. Rows are therefore never
 *   deleted by mirror id — those ids collide with other items' rows;
 * - its category memberships: each category in `cat_id` gets
 *   `anasheed_count - 1` (floored at 0); the categories themselves stay;
 * - the item row, and `anasheed - 1` on its group (floored at 0).
 *
 * Its frame file (`media/anasheed/frame/{bucket}/{id}.jpg`) is moved aside
 * before the transaction and put back if the transaction fails; it is removed
 * only after the commit. A failure to remove it then is reported (logged) but
 * cannot undo or corrupt the committed database state.
 *
 * Not touched: categories, groups, channels, authors (shared references); the
 * audit / legacy logs; `nuke_backup_booking` (a transient backup-worker
 * queue, not item data).
 */
final class DeleteAnasheedItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{group_id: int, title: string} */
    public function execute(AdminUser $actor, AnasheedItem $item, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'anasheed.delanasheed', $item);

        $itemId = (int) $item->id;
        $frame = UploadAnasheedItemFrameAction::absolutePath($itemId);
        $parked = null;
        if (is_file($frame)) {
            $parked = $frame.'.deleting-'.bin2hex(random_bytes(4));
            if (! @rename($frame, $parked)) {
                throw new RuntimeException("Cannot move the frame {$frame} aside");
            }
        }

        try {
            $result = DB::connection('main')->transaction(function () use ($actor, $itemId, $ip, $parked) {
                $main = DB::connection('main');
                $row = $main->table('nuke_anasheed_anasheed')->where('id', $itemId)->lockForUpdate()->first(['id', 'title', 'group_id', 'cat_id', 'frame']);
                abort_if($row === null, 404);

                $mirrorIds = $main->table('nuke_anasheed_mirror')->where('khid', $itemId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
                $comments = $main->table('nuke_anasheed_comments')->where('khid', $itemId)->count();
                $categories = array_values(array_unique(array_map('intval', CategoryList::parse($row->cat_id))));

                $main->table('nuke_anasheed_mirror')->where('khid', $itemId)->delete();
                $main->table('nuke_anasheed_comments')->where('khid', $itemId)->delete();
                $advanced = $main->table('nuke_anasheed_advanced')->where('id', $itemId)->delete();
                foreach ($categories as $categoryId) {
                    if ($categoryId > 0) {
                        CategoryCounter::decrement($categoryId, CategoryCounter::ANASHEED);
                    }
                }
                $main->table('nuke_anasheed_anasheed')->where('id', $itemId)->delete();

                $groupId = (int) $row->group_id;
                if ($groupId > 0) {
                    $main->table('nuke_anasheed_groups')->where('id', $groupId)->where('anasheed', '>', 0)->decrement('anasheed');
                }

                $this->audit->record($actor, 'del_item', 'anasheed', 'item', $itemId, [
                    'group_id' => $groupId,
                    'mirrors_deleted' => $mirrorIds,
                    'comments_deleted' => $comments,
                    'advanced_deleted' => $advanced,
                    'categories_released' => $categories,
                    'frame_file' => $parked !== null ? UploadAnasheedItemFrameAction::path($itemId) : null,
                ], $ip);

                return ['group_id' => $groupId, 'title' => (string) $row->title];
            });
        } catch (Throwable $e) {
            if ($parked !== null) {
                @rename($parked, $frame);
            }

            throw $e;
        }

        if ($parked !== null && ! @unlink($parked)) {
            report(new RuntimeException("Anasheed item {$itemId} deleted, but its frame file {$parked} could not be removed"));
        }

        return $result;
    }
}
