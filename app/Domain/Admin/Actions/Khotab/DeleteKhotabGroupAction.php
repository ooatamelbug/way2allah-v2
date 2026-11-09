<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use Illuminate\Support\Facades\DB;

/**
 * «حذف المجموعة» — OLD `KhotabDeleteGroup` → `ConfirmDeleteGroup`
 * (`modules/Khotab/admin/index.php:2826-2884`, production working copy).
 *
 * Exactly OLD's three writes: every item `WHERE group_id = X` gets
 * `group_id = 0`, every series `WHERE group_id = X` gets `group_id = 0`, and
 * the group row is deleted. Items keep their `ser_id`; nothing else moves —
 * OLD touched no counter, category or location, and neither does this.
 *
 * Corrections: authorized (`khotab.delgroup`), one transaction, audited.
 * The returned author and media type are where OLD sent the admin next.
 */
final class DeleteKhotabGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{author_id: int, vedio: int} */
    public function execute(AdminUser $actor, KhotabGroup $group, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.delgroup', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'author_id', 'vedio']);
            abort_if($row === null, 404);

            $itemIds = $main->table('nuke_islamic_khotab')->where('group_id', $group->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $seriesIds = $main->table('nuke_islamic_series')->where('group_id', $group->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

            $main->table('nuke_islamic_khotab')->where('group_id', $group->id)->update(['group_id' => 0]);
            $main->table('nuke_islamic_series')->where('group_id', $group->id)->update(['group_id' => 0]);
            $main->table('nuke_islamic_groups')->where('id', $group->id)->delete();

            $this->audit->record($actor, 'del_group', 'khotab', 'group', (int) $group->id, [
                'author_id' => (int) $row->author_id,
                'vedio' => (int) $row->vedio,
                'items_detached' => $itemIds,
                'series_detached' => $seriesIds,
            ], $ip);

            return ['author_id' => (int) $row->author_id, 'vedio' => (int) $row->vedio];
        });
    }
}
