<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * «حذف السلسلة» — OLD `KhotabDeleteSeries` → `ConfirmDeleteSeries`
 * (`modules/Khotab/admin/index.php:2223-2300`, production working copy).
 *
 * OLD refused while any item still has `ser_id = X` (hidden ones included)
 * — «عفواً .... لن تتمكن من مسح هذه السلسلة …» — and wrote nothing. Otherwise
 * it deleted the series row and nothing else (no counter, category or
 * location), then sent the admin to the series' group page, or to the
 * author's video / audio page when the series had no group.
 *
 * Corrections: authorized (`khotab.delseries`; OLD's `GreenZone('delseries')`
 * was inert), the check and the delete in one locked transaction, audited.
 */
final class DeleteKhotabSeriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{deleted: bool, items: int, group_id: int, author_id: int, vedio: int} */
    public function execute(AdminUser $actor, Series $series, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.delseries', $series);

        return DB::connection('main')->transaction(function () use ($actor, $series, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first(['id', 'group_id', 'author_id', 'vedio']);
            abort_if($row === null, 404);

            $result = [
                'deleted' => false,
                'items' => $main->table('nuke_islamic_khotab')->where('ser_id', $row->id)->count(),
                'group_id' => (int) $row->group_id,
                'author_id' => (int) $row->author_id,
                'vedio' => (int) $row->vedio,
            ];

            if ($result['items'] > 0) {
                return $result;
            }

            $main->table('nuke_islamic_series')->where('id', $row->id)->delete();

            $this->audit->record($actor, 'del_series', 'khotab', 'series', (int) $row->id, [
                'group_id' => $result['group_id'],
                'author_id' => $result['author_id'],
                'vedio' => $result['vedio'],
            ], $ip);

            return ['deleted' => true] + $result;
        });
    }
}
