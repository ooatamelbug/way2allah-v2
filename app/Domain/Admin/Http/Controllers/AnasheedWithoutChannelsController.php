<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * `anasheed_without_channels.htm` / `anasheed_without_channels-page-{page}.htm`
 * — OLD `anasheed_without_channels()` (`modules/Anasheed/index.php:345-387`,
 * production-hash-verified): an admin-only list of the Anasheed items without
 * a specific channel, 50 per page, newest id first, each with «تعديل».
 *
 * OLD counted and paged `channel_id IS NULL` but, on production (8,532 NULL
 * rows, always the paginated branch), listed `channel_id = 0 OR IS NULL OR
 * = ''` — `''` being `0` on the `int(11) NULL` column. Owner decision
 * (2026-10-29, `decision-log.md` #66): list that NULL + 0 set and page that
 * same set. Read-only: the only write reachable from here is the T4 edit,
 * which returns to the list page, as OLD's `redirct_to` did.
 */
class AnasheedWithoutChannelsController
{
    private const PER_PAGE = 50;

    public function show(?int $page = null): View
    {
        $items = AnasheedItem::query()
            ->where(fn (Builder $q) => $q->whereNull('channel_id')->orWhere('channel_id', 0))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['id', 'title'], 'page', $page);

        return view('admin.anasheed.without-channels', [
            'items' => $this->withLegacyUrls($items),
            'listPage' => self::listPage($items->currentPage()),
        ]);
    }

    /** A list page's canonical path: page 1 is the bare URL, never OLD's `-page-.htm`. */
    public static function listPage(int $page): string
    {
        return $page <= 1
            ? route('admin.anasheed.without-channels', absolute: false)
            : route('admin.anasheed.without-channels.paged', ['page' => $page], false);
    }

    /**
     * OLD's page links: `anasheed_without_channels-page-{n}.htm`
     * (`PS_Pagination` with `anasheed_without_channels-page-#.htm`).
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
                    ? route('admin.anasheed.without-channels')
                    : route('admin.anasheed.without-channels.paged', ['page' => $page]);
            }
        };
    }
}
