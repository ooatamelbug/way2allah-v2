<?php

namespace App\Domain\Admin\Actions\Categories;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Category;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * OLD Admin Migration Phase 3 — "اضف للكل" / "امسح من الكل" (add to all /
 * remove from all) on a Khotab series or group. Replaces OLD
 * `cat_add_to_sub{series,group}` and `cat_del_from_sub{series,group}`
 * (`modules/Cat/admin/index.php:966-1128`). `{$type}` is `series` or `group`
 * — the only two values any live call site passes (plan §17.1).
 *
 * Source-confirmed scope, reproduced exactly:
 *
 * | | items touched | then |
 * |---|---|---|
 * | series add    | every item `WHERE ser_id = X` (hidden included) lacking it | add to the series' own list |
 * | series remove | every item `WHERE ser_id = X` holding it                   | remove from the series' own list |
 * | group add     | items `WHERE group_id = X AND ser_id = 0` lacking it       | add to the group's own list |
 * | group remove  | items `WHERE group_id = X AND ser_id = 0` holding it       | **repair** the group (union of all its items) |
 *
 * The last row is an OLD asymmetry (`cat_del_from_subgroup` ends with
 * `cat_repair_group`, not `cat_del_from_group`) and is preserved. Bulk
 * item writes do not trigger the per-item cascade, as in OLD.
 *
 * Unlike OLD's statement-by-statement loop, the whole operation is **one
 * transaction**: every item, the container, the counter and every audit row
 * commit together or not at all. Only rows that change are written — items
 * that already hold (add) or lack (remove) the category are not touched, so
 * none of them is normalized.
 */
final class KhotabContainerCategoryAction
{
    private const CONTAINERS = [
        'series' => ['table' => 'nuke_islamic_series'],
        'group' => ['table' => 'nuke_islamic_groups'],
    ];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabParentCategoryRepair $repair,
    ) {}

    /** @return array{items_changed: int, container_changed: bool} */
    public function addToAll(AdminUser $actor, string $type, int $containerId, Category $category, ?string $ip = null): array
    {
        return $this->run($actor, $type, $containerId, $category, $ip, true);
    }

    /** @return array{items_changed: int, container_changed: bool} */
    public function removeFromAll(AdminUser $actor, string $type, int $containerId, Category $category, ?string $ip = null): array
    {
        return $this->run($actor, $type, $containerId, $category, $ip, false);
    }

    /** @return array{items_changed: int, container_changed: bool} */
    private function run(AdminUser $actor, string $type, int $containerId, Category $category, ?string $ip, bool $adding): array
    {
        if (! isset(self::CONTAINERS[$type])) {
            throw new InvalidArgumentException("Unknown container type '{$type}'; expected series or group.");
        }

        $this->authorizer->authorize($actor, 'khotab.addcat');

        return DB::connection('main')->transaction(function () use ($actor, $type, $containerId, $category, $ip, $adding) {
            $main = DB::connection('main');
            $table = self::CONTAINERS[$type]['table'];
            $categoryId = (int) $category->getKey();

            $container = $main->table($table)->where('id', $containerId)->lockForUpdate()->first(['id', 'cat']);
            abort_if($container === null, 404);

            $items = $main->table('nuke_islamic_khotab')
                ->when($type === 'series',
                    fn ($q) => $q->where('ser_id', $containerId),
                    fn ($q) => $q->where('group_id', $containerId)->where('ser_id', 0))
                ->orderBy('id')->lockForUpdate()->get(['id', 'cat']);

            $changedIds = [];

            foreach ($items as $item) {
                $tokens = CategoryList::parse($item->cat);

                if (CategoryList::contains($tokens, $categoryId) === $adding) {
                    continue;   // already in the wanted state: not touched, not normalized
                }

                $after = CategoryList::format($adding
                    ? CategoryList::withAdded($tokens, $categoryId)
                    : CategoryList::withRemoved($tokens, $categoryId));

                $main->table('nuke_islamic_khotab')->where('id', $item->id)->update(['cat' => $after]);

                $this->audit->record($actor, $adding ? 'add_cat_item' : 'del_cat_item', 'khotab', 'item', (int) $item->id, [
                    'category_id' => $categoryId,
                    'cat_before' => (string) $item->cat,
                    'cat_after' => $after,
                    'bulk' => $type,
                    'container_id' => $containerId,
                ], $ip);

                $changedIds[] = (int) $item->id;
            }

            $adding
                ? CategoryCounter::increment($categoryId, CategoryCounter::KHOTAB, count($changedIds))
                : CategoryCounter::decrement($categoryId, CategoryCounter::KHOTAB, count($changedIds));

            // The container's own list.
            if (! $adding && $type === 'group') {
                // OLD cat_del_from_subgroup ends in cat_repair_group.
                $before = (string) $container->cat;
                $this->repair->repair($actor, 'group', $containerId, $ip);
                $containerChanged = (string) $main->table($table)->where('id', $containerId)->value('cat') !== $before;
            } else {
                $containerChanged = $this->changeContainer($actor, $type, $table, $container, $categoryId, $adding, $changedIds, $ip);
            }

            return ['items_changed' => count($changedIds), 'container_changed' => $containerChanged];
        });
    }

    /**
     * OLD cat_add_to_series / cat_del_from_series / cat_add_to_group: change
     * the container's own list, moving video_count by one.
     *
     * @param  list<int>  $changedIds
     */
    private function changeContainer(AdminUser $actor, string $type, string $table, object $container, int $categoryId, bool $adding, array $changedIds, ?string $ip): bool
    {
        $tokens = CategoryList::parse($container->cat);

        if (CategoryList::contains($tokens, $categoryId) === $adding) {
            return false;
        }

        $after = CategoryList::format($adding
            ? CategoryList::withAdded($tokens, $categoryId)
            : CategoryList::withRemoved($tokens, $categoryId));

        DB::connection('main')->table($table)->where('id', $container->id)->update(['cat' => $after]);

        $adding
            ? CategoryCounter::increment($categoryId, CategoryCounter::KHOTAB)
            : CategoryCounter::decrement($categoryId, CategoryCounter::KHOTAB);

        $this->audit->record($actor, ($adding ? 'add_cat_' : 'del_cat_').$type, 'khotab', $type, (int) $container->id, [
            'category_id' => $categoryId,
            'cat_before' => (string) $container->cat,
            'cat_after' => $after,
            'items_changed' => $changedIds,
        ], $ip);

        return true;
    }
}
