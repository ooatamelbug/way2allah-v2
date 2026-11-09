<?php

namespace App\Domain\Admin\Actions\Categories;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 3 — add / remove one category on one Anasheed
 * item. Replaces OLD `cat_add_to_anasheed` and `cat_del_from_anasheed`
 * (`modules/Cat/admin/index.php:385-452`, `:632-676`).
 *
 * Differs from Khotab exactly where OLD did: the list is
 * `nuke_anasheed_anasheed.cat_id`, the counter is `anasheed_count`, and there
 * is **no** repair cascade (OLD's Anasheed operations called none). There is
 * no Anasheed junction table, so readers match `LIKE '%|id|%'` directly —
 * which is why the canonical leading pipe matters most here.
 */
final class AnasheedItemCategoryAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function add(AdminUser $actor, AnasheedItem $item, Category $category, ?string $ip = null): string
    {
        return $this->change($actor, $item, $category, $ip, true);
    }

    public function remove(AdminUser $actor, AnasheedItem $item, Category $category, ?string $ip = null): string
    {
        return $this->change($actor, $item, $category, $ip, false);
    }

    private function change(AdminUser $actor, AnasheedItem $item, Category $category, ?string $ip, bool $adding): string
    {
        $this->authorizer->authorize($actor, 'anasheed.addcat', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $category, $ip, $adding) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_anasheed')->where('id', $item->getKey())->lockForUpdate()
                ->first(['id', 'cat_id']);

            abort_if($row === null, 404);

            $categoryId = (int) $category->getKey();
            $tokens = CategoryList::parse($row->cat_id);
            $present = CategoryList::contains($tokens, $categoryId);

            if ($adding === $present) {
                return $adding ? KhotabItemCategoryAction::ALREADY_PRESENT : KhotabItemCategoryAction::ABSENT;
            }

            $after = CategoryList::format($adding
                ? CategoryList::withAdded($tokens, $categoryId)
                : CategoryList::withRemoved($tokens, $categoryId));

            $main->table('nuke_anasheed_anasheed')->where('id', $row->id)->update(['cat_id' => $after]);

            $adding
                ? CategoryCounter::increment($categoryId, CategoryCounter::ANASHEED)
                : CategoryCounter::decrement($categoryId, CategoryCounter::ANASHEED);

            $this->audit->record($actor, $adding ? 'add_cat_item' : 'del_cat_item', 'anasheed', 'item', (int) $row->id, [
                'category_id' => $categoryId,
                'cat_before' => (string) $row->cat_id,
                'cat_after' => $after,
            ], $ip);

            return $adding ? KhotabItemCategoryAction::ADDED : KhotabItemCategoryAction::REMOVED;
        });
    }
}
