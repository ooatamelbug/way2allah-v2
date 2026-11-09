<?php

namespace App\Domain\Admin\Actions\Categories;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 3 — OLD `cat_repair_series` / `cat_repair_group`
 * (`modules/Cat/admin/index.php:904-965`), the cascade that runs after every
 * Khotab item-level category change and at the end of a bulk group removal.
 *
 * Recomputes the parent's list as the union of **all** its items' lists
 * (`ser_id = X` for a series, `group_id = X` for a group — every item in both
 * cases) and overwrites the parent. So, preserved from OLD: a category
 * assigned directly to a series or group survives only until the next
 * item-level change, unless one of its items also carries it.
 *
 * One difference, by the Phase 3 format rule: the parent is written **only if
 * its set of ids actually changes**. OLD rewrote it unconditionally, which
 * here would re-format an unchanged legacy row purely to normalize it. When it
 * is written, it is written canonically, and audited. Counters are not
 * touched (OLD's repair never touched them).
 */
final class KhotabParentCategoryRepair
{
    private const PARENTS = [
        'series' => ['table' => 'nuke_islamic_series', 'item_key' => 'ser_id'],
        'group' => ['table' => 'nuke_islamic_groups', 'item_key' => 'group_id'],
    ];

    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function repair(AdminUser $actor, string $type, int $parentId, ?string $ip): void
    {
        if ($parentId < 1 || ! isset(self::PARENTS[$type])) {
            return;
        }

        $main = DB::connection('main');
        ['table' => $table, 'item_key' => $itemKey] = self::PARENTS[$type];

        $parent = $main->table($table)->where('id', $parentId)->lockForUpdate()->first(['id', 'cat']);

        if ($parent === null) {
            return;   // A missing parent is tolerated, as in OLD.
        }

        $union = CategoryList::union(
            $main->table('nuke_islamic_khotab')->where($itemKey, $parentId)->orderBy('id')->pluck('cat')
        );
        $current = CategoryList::parse($parent->cat);

        if (CategoryList::sameSet($current, $union)) {
            return;
        }

        $after = CategoryList::format($union);
        $main->table($table)->where('id', $parentId)->update(['cat' => $after]);

        $this->audit->record($actor, "repair_cat_{$type}", 'khotab', $type, $parentId, [
            'cat_before' => (string) $parent->cat,
            'cat_after' => $after,
        ], $ip);
    }
}
