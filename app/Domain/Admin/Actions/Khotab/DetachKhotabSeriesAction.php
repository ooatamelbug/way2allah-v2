<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * The icon beside each series on a group page — OLD `KhotabUpSeries` →
 * `UpSeries` (`modules/Khotab/admin/index.php:2210-2222`, production working
 * copy): take the series out of its group.
 *
 * Exactly OLD's two writes: the series gets `group_id = 0`, and every item
 * `WHERE ser_id = X` gets `group_id = 0`. No counter, category or location
 * changes — OLD made none.
 *
 * Authorization: OLD allowed `radminsuper` plus two hard-coded admin names.
 * The names are not reproduced (owner instruction); the operation is gated
 * by `khotab.moveitem` («نقل مادة»), and super-admin passes as everywhere.
 * OLD ran it from a GET link; here it is a CSRF-protected POST, audited.
 */
final class DetachKhotabSeriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return int the group the series left (0 if it had none) */
    public function execute(AdminUser $actor, Series $series, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.moveitem', $series);

        return DB::connection('main')->transaction(function () use ($actor, $series, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first(['id', 'group_id']);
            abort_if($row === null, 404);

            $itemIds = $main->table('nuke_islamic_khotab')->where('ser_id', $series->id)->where('group_id', '<>', 0)
                ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

            $main->table('nuke_islamic_series')->where('id', $series->id)->update(['group_id' => 0]);
            $main->table('nuke_islamic_khotab')->where('ser_id', $series->id)->update(['group_id' => 0]);

            $this->audit->record($actor, 'detach_series', 'khotab', 'series', (int) $series->id, [
                'group_id_before' => (int) $row->group_id,
                'items_detached' => $itemIds,
            ], $ip);

            return (int) $row->group_id;
        });
    }
}
