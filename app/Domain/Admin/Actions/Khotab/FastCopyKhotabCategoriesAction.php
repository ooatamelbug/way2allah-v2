<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Actions\Categories\KhotabParentCategoryRepair;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use Illuminate\Support\Facades\DB;

/**
 * «التصنيف السريع» on a group page — OLD `cat_fastcopy` /
 * `cat_fastcopy_confirm` (`modules/Cat/admin/index.php:520-783`, production
 * = this working copy, plan §19.1), in the group context the group page links
 * to (`Author = group author`, `Group = X`, `Series = 0`).
 *
 * The matrix: rows are the group's direct items (`author = A AND ser_id = 0
 * AND group_id = X`, ordered `weight DESC`); columns are the categories in
 * the GROUP's own `cat` list that exist in `nuke_w2a_cat`. With no such
 * category OLD showed «التصنيف السريع غير متاح …» instead.
 *
 * Save, as OLD: each item's list becomes exactly its ticked boxes — only ids
 * that are group categories survive, in the group's order — so a category on
 * the item that is NOT one of the group's columns is dropped (OLD's
 * behavior). An item is written only when its set of ids changes (OLD's
 * `array_diff` test). OLD moved no category counter here, and neither does
 * this. Then the group's own list is repaired (OLD `cat_repair_group`).
 *
 * Format, per the owner's Phase 3 decision (plan §20): written canonically
 * (`|3|17|`); an emptied list is stored as `''` where OLD wrote `'|'`.
 *
 * Corrections: authorized (`khotab.addcat`, the key that already gates this
 * Cat module's operations — OLD's module-level check), one transaction,
 * audited per changed item.
 */
final class FastCopyKhotabCategoriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabParentCategoryRepair $repair,
    ) {}

    /**
     * The matrix columns: the group's categories that exist, in its order.
     *
     * @return array<int, string> category id => title
     */
    public function columns(KhotabGroup $group): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', CategoryList::parse((string) $group->getAttribute('cat'))),
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
    public function execute(AdminUser $actor, KhotabGroup $group, array $submitted, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addcat', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $submitted, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'author_id', 'cat']);
            abort_if($row === null, 404);

            // OLD's own filter: a ticked value survives only if it is one of the
            // group's (raw) list entries and its key equals its value.
            $groupTokens = CategoryList::parse((string) $row->cat);

            $items = $main->table('nuke_islamic_khotab')
                ->where('ser_id', 0)->where('group_id', $row->id)->where('author', $row->author_id)
                ->orderBy('id')->lockForUpdate()->get(['id', 'cat']);

            $changed = 0;

            foreach ($items as $item) {
                $ticked = $submitted[$item->id] ?? [];
                $ticked = is_array($ticked) ? $ticked : [];

                $new = [];
                foreach ($ticked as $key => $value) {
                    if (is_scalar($value) && in_array((string) $value, $groupTokens, true) && (string) $value === (string) $key) {
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
                    'group_id' => (int) $row->id,
                    'cat_before' => (string) $item->cat,
                    'cat_after' => $after,
                    'added' => array_values(array_diff($new, $old)),
                    'removed' => array_values(array_diff($old, $new)),
                ], $ip);

                $changed++;
            }

            $this->repair->repair($actor, 'group', (int) $row->id, $ip);

            return $changed;
        });
    }
}
