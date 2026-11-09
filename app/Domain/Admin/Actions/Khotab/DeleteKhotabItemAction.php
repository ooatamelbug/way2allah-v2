<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * «مسح» on the item page — OLD `KhotabDeleteKhotab` → `ConfirmDeleteKhotab`
 * (`modules/Khotab/admin/index.php:1717-1811`, production working copy).
 *
 * OLD's writes, reproduced exactly and nothing more: the item row is deleted;
 * its group and series `count - 1`; the author's `vedio` (video) or `audio`
 * - 1; then update_series_location, update_group_location and
 * update_author_location at the item's location. OLD left the item's
 * mirrors, comments, uploaded files and category counts as they were, and so
 * does this. OLD sent the admin to the series page, else the group page,
 * else the author's video / audio page.
 *
 * Corrections: authorized (`khotab.delitem`; OLD's `GreenZone('delkhotab')`
 * was inert), one locked transaction, audited.
 */
final class DeleteKhotabItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
    ) {}

    /** @return array{title: string, ser_id: int, group_id: int, author_id: int, vedio: int} */
    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.delitem', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()
                ->first(['id', 'title', 'ser_id', 'group_id', 'author', 'location_id', 'vedio']);
            abort_if($row === null, 404);

            $result = [
                'title' => (string) $row->title,
                'ser_id' => (int) $row->ser_id,
                'group_id' => (int) $row->group_id,
                'author_id' => (int) $row->author,
                'vedio' => (int) $row->vedio,
            ];
            $location = (int) $row->location_id;

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->delete();
            if ($result['group_id'] > 0) {
                $main->table('nuke_islamic_groups')->where('id', $result['group_id'])->update(['count' => DB::raw('count-1')]);
            }
            if ($result['ser_id'] > 0) {
                $main->table('nuke_islamic_series')->where('id', $result['ser_id'])->update(['count' => DB::raw('count-1')]);
            }

            $type = $result['vedio'] === 1 ? 'vedio' : 'audio';
            $main->table('nuke_islamic_authors')->where('id', $result['author_id'])->update([$type => DB::raw("{$type}-1")]);

            $this->locations->update('series', $result['ser_id'], $location);
            $this->locations->update('group', $result['group_id'], $location);
            $this->locations->update('author', $result['author_id'], $location);

            $this->audit->record($actor, 'del_item', 'khotab', 'item', (int) $row->id, [
                'ser_id' => $result['ser_id'],
                'group_id' => $result['group_id'],
                'author_id' => $result['author_id'],
                'vedio' => $result['vedio'],
                'location_id' => $location,
                'title' => AuditText::digest($result['title']),
            ], $ip);

            return $result;
        });
    }
}
