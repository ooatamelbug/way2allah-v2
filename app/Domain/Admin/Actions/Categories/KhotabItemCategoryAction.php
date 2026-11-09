<?php

namespace App\Domain\Admin\Actions\Categories;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 3 — add / remove one category on one Khotab
 * item. Replaces OLD `cat_add_to_item` and `cat_del_from_item`
 * (`modules/Cat/admin/index.php:325-485`).
 *
 * Per change: write `nuke_islamic_khotab.cat` canonically, move
 * `nuke_w2a_cat.video_count`, audit, then run OLD's cascade — repair the
 * item's group and series (KhotabParentCategoryRepair) when they are set.
 * One `main` transaction holds all of it.
 *
 * `khotab_category_index` is **not** written here. A DB trigger on
 * `nuke_islamic_khotab` maintains it from the `cat` column, and it already
 * tolerates either list format (`7amlat/db_migration.sql:196-256`).
 *
 * No-ops are true no-ops: a category already present (add) or absent
 * (remove) causes no write, no counter change, no audit row and no
 * normalization of the stored string — as in OLD.
 */
final class KhotabItemCategoryAction
{
    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public const ALREADY_PRESENT = 'already_present';

    public const ABSENT = 'absent';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabParentCategoryRepair $repair,
    ) {}

    public function add(AdminUser $actor, KhotabItem $item, Category $category, ?string $ip = null): string
    {
        return $this->change($actor, $item, $category, $ip, true);
    }

    public function remove(AdminUser $actor, KhotabItem $item, Category $category, ?string $ip = null): string
    {
        return $this->change($actor, $item, $category, $ip, false);
    }

    private function change(AdminUser $actor, KhotabItem $item, Category $category, ?string $ip, bool $adding): string
    {
        $this->authorizer->authorize($actor, 'khotab.addcat', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $category, $ip, $adding) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->getKey())->lockForUpdate()
                ->first(['id', 'cat', 'group_id', 'ser_id']);

            abort_if($row === null, 404);

            $categoryId = (int) $category->getKey();
            $tokens = CategoryList::parse($row->cat);
            $present = CategoryList::contains($tokens, $categoryId);

            if ($adding === $present) {
                return $adding ? self::ALREADY_PRESENT : self::ABSENT;
            }

            $after = CategoryList::format($adding
                ? CategoryList::withAdded($tokens, $categoryId)
                : CategoryList::withRemoved($tokens, $categoryId));

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['cat' => $after]);

            $adding
                ? CategoryCounter::increment($categoryId, CategoryCounter::KHOTAB)
                : CategoryCounter::decrement($categoryId, CategoryCounter::KHOTAB);

            $this->audit->record($actor, $adding ? 'add_cat_item' : 'del_cat_item', 'khotab', 'item', (int) $row->id, [
                'category_id' => $categoryId,
                'cat_before' => (string) $row->cat,
                'cat_after' => $after,
            ], $ip);

            // OLD's cascade, in OLD's order: group first, then series.
            $this->repair->repair($actor, 'group', (int) $row->group_id, $ip);
            $this->repair->repair($actor, 'series', (int) $row->ser_id, $ip);

            return $adding ? self::ADDED : self::REMOVED;
        });
    }
}
