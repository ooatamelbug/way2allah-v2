<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * `anasheed_without_cats.htm` / `anasheed_without_cats-page-{page}.htm` —
 * OLD `anasheed_without_cats()` (`modules/Anasheed/index.php:259-301`,
 * production-hash-verified): an admin-only list of the Anasheed items that
 * have no category, 50 per page, newest id first, each with «إضافة تصنيف».
 *
 * Read-only: the only write reachable from here is the existing T7 category
 * assignment. Owner decisions (2026-10-29, `decision-log.md` #64):
 * `anasheed.addcat` on the route, and a successful add returns to the item.
 */
class AnasheedWithoutCatsController
{
    private const PER_PAGE = 50;

    public function show(?int $page = null): View
    {
        $items = AnasheedItem::query()
            ->where(fn (Builder $q) => $q->whereNull('cat_id')->orWhere('cat_id', '')->orWhere('cat_id', '|'))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['id', 'title'], 'page', $page);

        return view('admin.anasheed.without-cats', ['items' => $this->withLegacyUrls($items)]);
    }

    /**
     * OLD's page links: `anasheed_without_cats-page-{n}.htm` (`PS_Pagination`
     * with `anasheed_without_cats-page-#.htm`), page 1 being the bare URL.
     *
     * @param  LengthAwarePaginator<int, AnasheedItem>  $items
     * @return LengthAwarePaginator<int, AnasheedItem>
     */
    private function withLegacyUrls(LengthAwarePaginator $items): LengthAwarePaginator
    {
        return new class($items->items(), $items->total(), $items->perPage(), $items->currentPage()) extends LengthAwarePaginator
        {
            public function url($page)
            {
                return $page <= 1
                    ? route('admin.anasheed.without-cats')
                    : route('admin.anasheed.without-cats.paged', ['page' => $page]);
            }
        };
    }
}
