<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * «إعادة ترتيب» on a series page — OLD `KhotabReArrange` → `ReArrange` /
 * `ConfirmReArrange` (`modules/Khotab/admin/index.php:1813-1888`, production
 * working copy), in the context the series page links to (`Author = series
 * author`, `Group =` {@see KhotabSeriesContext::groupId()}, `Series = X`).
 *
 * The list is OLD's: items `WHERE author = A AND ser_id = X AND group_id = G
 * ORDER BY weight DESC`, hidden ones included, numbered N down to 1. On save
 * OLD `asort()`s the submitted numbers and writes `weight = 1, 2, …` in that
 * order, each `WHERE id = … AND ser_id = X AND group_id = G`; the same
 * `asort()` is used here, so ties and odd input sort as OLD sorted them. An
 * id outside the list matches nothing but still consumes a position, as in
 * OLD. OLD then returned to the series page.
 *
 * Corrections: authorized (`khotab.sortitems`; OLD's `GreenZone('rearrange')`
 * was inert), one transaction, audited.
 */
final class ReArrangeKhotabSeriesItemsAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<int|string, mixed>  $submitted  OLD `Khotab[id] => number`
     * @return int rows whose weight was written
     */
    public function execute(AdminUser $actor, Series $series, array $submitted, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.sortitems', $series);

        if ($submitted === []) {
            return 0;   // OLD returned without doing anything.
        }

        $values = array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $submitted);
        asort($values);

        return DB::connection('main')->transaction(function () use ($actor, $series, $values, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first(['id', 'group_id']);
            abort_if($row === null, 404);
            $groupId = KhotabSeriesContext::groupId((int) $row->group_id);

            $order = [];
            $written = 0;
            $weight = 1;

            foreach (array_keys($values) as $id) {
                $id = (int) $id;
                $written += $main->table('nuke_islamic_khotab')
                    ->where('id', $id)->where('ser_id', $row->id)->where('group_id', $groupId)
                    ->update(['weight' => $weight]);
                $order[] = ['id' => $id, 'weight' => $weight];
                $weight++;
            }

            $this->audit->record($actor, 'rearrange_item', 'khotab', 'series', (int) $row->id, [
                'group_id' => $groupId,
                'weights' => $order,
                'rows_matched' => $written,
            ], $ip);

            return $written;
        });
    }
}
