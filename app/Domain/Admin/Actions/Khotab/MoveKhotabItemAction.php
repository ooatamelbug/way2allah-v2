<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نقل» on the item page — OLD `KhotabMoveKhotab` → `MoveKhotab(…, confirm=1)`
 * (`modules/Khotab/admin/index.php:1284-1420`, production working copy).
 *
 * OLD's checks, all reported together as OLD did: the author must exist; a
 * group, when chosen, must exist with the item's media type; a series, when
 * chosen, must exist in the chosen group with the item's media type. OLD did
 * not check that the group or series belongs to the chosen author, and
 * neither does this.
 *
 * Then OLD's writes, in OLD's order:
 *
 * 1. old group `count - 1`, new group `count + 1`, old series `count - 1`,
 *    new series `count + 1` (each only when its id > 0);
 * 2. the item's `author`, `ser_id`, `group_id`;
 * 3. when the author changed: with a location, update_author_location for
 *    the new then the old author; the new author's `vedio`/`audio` + 1 and
 *    the old author's - 1;
 * 4. with a location: update_group_location (old, new) and
 *    update_series_location (old, new).
 *
 * Corrections: authorized (`khotab.moveitem`; OLD's `GreenZone('movekhotab')`
 * was inert), one locked transaction, audited.
 */
final class MoveKhotabItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
    ) {}

    /** @throws ValidationException OLD's refusals */
    public function execute(AdminUser $actor, KhotabItem $item, int $authorId, int $groupId, int $serId, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.moveitem', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $authorId, $groupId, $serId, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()
                ->first(['id', 'author', 'group_id', 'ser_id', 'vedio', 'location_id']);
            abort_if($row === null, 404);

            $vedio = (int) $row->vedio;
            $errors = [];
            if (! $main->table('nuke_islamic_authors')->where('id', $authorId)->exists()) {
                $errors[] = 'خطأ في الداعية المختار';
            }
            if ($groupId > 0 && ! $main->table('nuke_islamic_groups')->where('id', $groupId)->where('vedio', $vedio)->exists()) {
                $errors[] = 'خطأ في المجموعة المختارة';
            }
            if ($serId > 0 && ! $main->table('nuke_islamic_series')->where('id', $serId)->where('group_id', $groupId)->where('vedio', $vedio)->exists()) {
                $errors[] = 'خطأ في السلسلة المختارة';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages(['author_id' => $errors]);
            }

            $oldGroup = (int) $row->group_id;
            $oldSeries = (int) $row->ser_id;
            $oldAuthor = (int) $row->author;
            $location = (int) $row->location_id;

            foreach ([['nuke_islamic_groups', $oldGroup, '-'], ['nuke_islamic_groups', $groupId, '+'], ['nuke_islamic_series', $oldSeries, '-'], ['nuke_islamic_series', $serId, '+']] as [$table, $id, $sign]) {
                if ($id > 0) {
                    $main->table($table)->where('id', $id)->update(['count' => DB::raw("count{$sign}1")]);
                }
            }

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['author' => $authorId, 'ser_id' => $serId, 'group_id' => $groupId]);

            if ($authorId !== $oldAuthor) {
                $type = $vedio === 0 ? 'audio' : 'vedio';
                if ($location > 0) {
                    $this->locations->update('author', $authorId, $location);
                    $this->locations->update('author', $oldAuthor, $location);
                }
                $main->table('nuke_islamic_authors')->where('id', $authorId)->update([$type => DB::raw("{$type}+1")]);
                if ($oldAuthor > 0) {
                    $main->table('nuke_islamic_authors')->where('id', $oldAuthor)->update([$type => DB::raw("{$type}-1")]);
                }
            }

            if ($location > 0) {
                $this->locations->update('group', $oldGroup, $location);
                $this->locations->update('group', $groupId, $location);
                $this->locations->update('series', $oldSeries, $location);
                $this->locations->update('series', $serId, $location);
            }

            $this->audit->record($actor, 'move_item', 'khotab', 'item', (int) $row->id, [
                'author' => ['from' => $oldAuthor, 'to' => $authorId],
                'group_id' => ['from' => $oldGroup, 'to' => $groupId],
                'ser_id' => ['from' => $oldSeries, 'to' => $serId],
                'location_id' => $location,
            ], $ip);
        });
    }
}
