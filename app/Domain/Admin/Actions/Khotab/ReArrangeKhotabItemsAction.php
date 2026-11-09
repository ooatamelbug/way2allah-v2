<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use Illuminate\Support\Facades\DB;

/**
 * «إعادة ترتيب» on a group page — OLD `KhotabReArrange` → `ReArrange` /
 * `ConfirmReArrange` (`modules/Khotab/admin/index.php:1813-1888`, production
 * working copy), in the group context the group page links to
 * (`Author = group author`, `Group = X`, `Series = 0`).
 *
 * The list is OLD's: items `WHERE author = A AND ser_id = 0 AND group_id = X
 * ORDER BY weight DESC`, hidden ones included, each shown with a number from
 * N down to 1. On save OLD `asort()`s the submitted numbers (PHP's default
 * comparison, equal values keep their submitted order) and writes
 * `weight = 1, 2, …` in that order, each `WHERE id = … AND ser_id = 0 AND
 * group_id = X`. The same `asort()` is used here, so ties, blanks and
 * non-numeric input sort exactly as OLD sorted them. An id that is not one of
 * the group's direct items matches nothing, as in OLD (it still consumes a
 * weight position, as in OLD).
 *
 * Corrections: authorized (`khotab.sortitems` — menu key «ترتيب المواد»; OLD
 * called the inert `GreenZone('rearrange')`), one transaction, audited.
 */
final class ReArrangeKhotabItemsAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<int|string, mixed>  $submitted  OLD `Khotab[id] => number`
     * @return int rows whose weight was written
     */
    public function execute(AdminUser $actor, KhotabGroup $group, array $submitted, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.sortitems', $group);

        if ($submitted === []) {
            return 0;   // OLD returned without doing anything.
        }

        $values = array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $submitted);
        asort($values);

        return DB::connection('main')->transaction(function () use ($actor, $group, $values, $ip) {
            $main = DB::connection('main');
            $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first(['id']) ?? abort(404);

            $order = [];
            $written = 0;
            $weight = 1;

            foreach (array_keys($values) as $id) {
                $id = (int) $id;
                $written += $main->table('nuke_islamic_khotab')
                    ->where('id', $id)->where('ser_id', 0)->where('group_id', $group->id)
                    ->update(['weight' => $weight]);
                $order[] = ['id' => $id, 'weight' => $weight];
                $weight++;
            }

            $this->audit->record($actor, 'rearrange_item', 'khotab', 'group', (int) $group->id, [
                'weights' => $order,
                'rows_matched' => $written,
            ], $ip);

            return $written;
        });
    }
}
