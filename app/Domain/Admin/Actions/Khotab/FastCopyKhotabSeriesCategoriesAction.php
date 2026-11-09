<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Actions\Categories\KhotabParentCategoryRepair;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * «التصنيف السريع» on a series page — OLD `cat_fastcopy` /
 * `cat_fastcopy_confirm` (`modules/Cat/admin/index.php:520-783`, production
 * working copy), in the context the series page links to (`Author = series
 * author`, `Group =` {@see KhotabSeriesContext::groupId()}, `Series = X`).
 *
 * The matrix: rows are the items `author = A AND ser_id = X AND group_id = G`
 * (`weight DESC`); columns are the categories in the SERIES' own `cat` list
 * that exist in `nuke_w2a_cat`. With none, OLD showed «التصنيف السريع غير
 * متاح …».
 *
 * Save, as OLD: each item's list becomes exactly its ticked boxes — only ids
 * that are series categories survive — written only when its set changes;
 * no category counter moves. Then OLD repaired the group's list (when `G > 0`)
 * and the series' list, in that order (`cat_repair_group`,
 * `cat_repair_series`).
 *
 * Format, per the owner's Phase 3 decision (plan §20): written canonically;
 * an emptied list is stored as `''` where OLD wrote `'|'`.
 *
 * Corrections: authorized (`khotab.addcat`, the key gating this Cat module's
 * operations), one transaction, audited per changed item.
 */
final class FastCopyKhotabSeriesCategoriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabParentCategoryRepair $repair,
    ) {}

    /**
     * The matrix columns: the series' categories that exist, in its order.
     *
     * @return array<int, string> category id => title
     */
    public function columns(Series $series): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', CategoryList::parse((string) $series->getAttribute('cat'))),
            static fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $titles = DB::connection('main')->table('nuke_w2a_cat')->whereIn('id', $ids)->pluck('title', 'id');

        $columns = [];
        foreach ($ids as $id) {
            if ($titles->has($id)) {
                $columns[$id] = (string) $titles[$id];
            }
        }

        return $columns;
    }

    /**
     * @param  array<int|string, mixed>  $submitted  OLD `Khotab[item][cat] => cat`
     * @return int items changed
     */
    public function execute(AdminUser $actor, Series $series, array $submitted, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addcat', $series);

        return DB::connection('main')->transaction(function () use ($actor, $series, $submitted, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first(['id', 'author_id', 'group_id', 'cat']);
            abort_if($row === null, 404);
            $groupId = KhotabSeriesContext::groupId((int) $row->group_id);

            // OLD's own filter: a ticked value survives only if it is one of the
            // series' (raw) list entries and its key equals its value.
            $seriesTokens = CategoryList::parse((string) $row->cat);

            $items = $main->table('nuke_islamic_khotab')
                ->where('ser_id', $row->id)->where('group_id', $groupId)->where('author', $row->author_id)
                ->orderBy('id')->lockForUpdate()->get(['id', 'cat']);

            $changed = 0;

            foreach ($items as $item) {
                $ticked = $submitted[$item->id] ?? [];
                $ticked = is_array($ticked) ? $ticked : [];

                $new = [];
                foreach ($ticked as $key => $value) {
                    if (is_scalar($value) && in_array((string) $value, $seriesTokens, true) && (string) $value === (string) $key) {
                        $new[] = (string) $value;
                    }
                }

                $old = CategoryList::parse((string) $item->cat);

                if (CategoryList::sameSet($old, $new)) {
                    continue;
                }

                $after = CategoryList::format($new);
                $main->table('nuke_islamic_khotab')->where('id', $item->id)->update(['cat' => $after]);

                $this->audit->record($actor, 'fast_copy', 'khotab', 'item', (int) $item->id, [
                    'series_id' => (int) $row->id,
                    'group_id' => $groupId,
                    'cat_before' => (string) $item->cat,
                    'cat_after' => $after,
                    'added' => array_values(array_diff($new, $old)),
                    'removed' => array_values(array_diff($old, $new)),
                ], $ip);

                $changed++;
            }

            if ($groupId > 0) {
                $this->repair->repair($actor, 'group', $groupId, $ip);
            }
            $this->repair->repair($actor, 'series', (int) $row->id, $ip);

            return $changed;
        });
    }
}
