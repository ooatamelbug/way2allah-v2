<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/series.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * `series.php` never sets `$ob->mode`, so `ListKhotab()`'s `else` (default,
 * IF-005-shaped unconditional-filter) branch is the one reached —
 * `ContentListingService::khotabItemsDefault()`, not the `'fixed'/'new'`
 * branch.
 *
 * IF-015's fix: both sidebar boxes use the series' own author
 * consistently (`series.php:146` incorrectly used `$Group->author_id`,
 * which is undefined when the series has no group — this controller
 * always uses the series' `author_id`, matching the "Newest" box's
 * already-correct behavior).
 */
class KhotabSeriesController
{
    public function show(int $series, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $seriesModel = Series::where('hidden', 0)->findOrFail($series);

        $video = (bool) $seriesModel->vedio;

        $items = $listing->khotabItemsDefault($seriesModel->author_id, $series, $seriesModel->group_id, $video);
        $mostDownloaded = $sidebar->khotabMostDownloadedByAuthor($seriesModel->author_id, $video);
        $mostRecent = $sidebar->khotabMostRecentByAuthor($seriesModel->author_id, $video);
        $randomFeatured = $sidebar->khotabRandomFeatured();

        return view('khotab.series', compact('seriesModel', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured'));
    }
}
