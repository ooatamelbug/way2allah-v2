<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * `anasheed_without_authors.htm` / `anasheed_without_authors-page-{page}.htm`
 * — OLD `anasheed_without_authors()` (`modules/Anasheed/index.php:303-345`,
 * production-hash-verified): an admin-only list of the Anasheed items whose
 * `author_id IS NULL` (never `0`, «بدون شيخ»), 50 per page, newest id first,
 * each with «تعديل».
 *
 * Read-only: the only write reachable from here is the existing T4 edit,
 * which returns to the list page after a successful save, as OLD's
 * `redirct_to` did. Owner decisions (2026-10-29, `decision-log.md` #65):
 * `anasheed.editanasheed` on the route, and the return to the same list page.
 */
class AnasheedWithoutAuthorsController
{
    private const PER_PAGE = 50;

    public function show(?int $page = null): View
    {
        $items = AnasheedItem::query()
            ->whereNull('author_id')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['id', 'title'], 'page', $page);

        return view('admin.anasheed.without-authors', [
            'items' => $this->withLegacyUrls($items),
            'listPage' => self::listPage($items->currentPage()),
        ]);
    }

    /** A list page's canonical path: page 1 is the bare URL, never OLD's `-page-.htm`. */
    public static function listPage(int $page): string
    {
        return $page <= 1
            ? route('admin.anasheed.without-authors', absolute: false)
            : route('admin.anasheed.without-authors.paged', ['page' => $page], false);
    }

    /**
     * OLD's page links: `anasheed_without_authors-page-{n}.htm`
     * (`PS_Pagination` with `anasheed_without_authors-page-#.htm`).
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
                    ? route('admin.anasheed.without-authors')
                    : route('admin.anasheed.without-authors.paged', ['page' => $page]);
            }
        };
    }
}
