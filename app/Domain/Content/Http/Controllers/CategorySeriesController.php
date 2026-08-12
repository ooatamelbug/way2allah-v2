<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `categories/series.php` (`category-series-{ser_id}-{cat_id}.htm`,
 * `.htaccess:175` — note the URL's own segment order: `ser_id` first,
 * `cat_id` second). Confirmed real, live route with a real target file
 * (not the `modules.php`-dead-dispatcher pattern) — previously deferred
 * only because it depended on `khotab_category_index`, now populated
 * (`var-category`/`categories.htm` closure rounds).
 *
 * Main listing reuses the EXISTING `ContentListingService::
 * khotabItemsByCategory()` as-is — `series.php:52`'s
 * `ListKhotab($cat_id, $author_id, 0, $ser_id, $video)` call is the exact
 * same function/signature `category.php` already uses
 * (`categories/functions.php:318`: `ListKhotab($id=0, $author_id=0,
 * $group_id=0, $ser_id=0, $video=0)`), just with a real `$ser_id` instead
 * of 0 — no new query needed. `$author_id` is read here but never
 * assigned anywhere in `series.php` (a genuine undefined-variable use);
 * moot regardless, since `ListKhotab()`'s own body never references its
 * `$author_id` parameter (already confirmed, `CategoryController`'s own
 * docblock, P-016-shaped).
 *
 * Sidebar reuses `khotabRandomFeatured()` (identical `randomitems()` call)
 * but NOT `khotabMostDownloadedByCategory()`/`khotabMostRecentByCategory()`
 * — this page's own `topitems()` calls omit the `hidden=0` filter those
 * methods have (confirmed by direct reading); uses the new
 * `khotabMostDownloadedByCategoryForSeries()`/`khotabMostRecentByCategoryForSeries()`
 * instead.
 *
 * `.grx` GetRight bulk-download links (`series.php:84,89`) are reproduced
 * as real hrefs, matching the sitewide "keep real links to not-yet-built
 * routes" convention — not built here, same deferred-scope treatment as
 * `khotab_send_friend()`/`categories/downitems.php` elsewhere in this
 * codebase. The download-icon image's hardcoded `http://www.way2allah.com/`
 * domain is NOT reproduced (same P-018 anti-pattern already excluded
 * elsewhere, e.g. `ChannelController`'s docblock) — a relative path is
 * used instead; note `images/` itself is not yet symlinked into `public/`
 * in this local environment (only `assets/`/`css/`/`w2a_autocomplete/`
 * are), so this one icon will 404 locally until that separate,
 * broader asset-wiring gap is closed — not attempted here, out of scope
 * for this single-capability pass.
 */
class CategorySeriesController
{
    public function show(int $series, int $category, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $seriesModel = Series::findOrFail($series);
        $categoryModel = Category::findOrFail($category);

        $items = $listing->khotabItemsByCategory($category, $series, 0, true);

        $randomFeatured = $sidebar->khotabRandomFeatured();
        $mostDownloaded = $sidebar->khotabMostDownloadedByCategoryForSeries($category);
        $mostRecent = $sidebar->khotabMostRecentByCategoryForSeries($category);

        $categoryBreadcrumbTrail = $categoryModel->breadcrumbTrail();

        // series.php:64 Ser_Cat_Breadcrumb($Series->cat, ...) — one full
        // breadcrumb trail per pipe-delimited category id on the SERIES's
        // own `cat` column (distinct from $categoryModel, the single
        // category this specific URL was reached through), only rendered
        // when the main listing has rows (series.php:55's own condition).
        $seriesCategoryTrails = collect();
        if ($items->isNotEmpty() && ! empty($seriesModel->cat)) {
            $seriesCategoryTrails = collect(explode('|', $seriesModel->cat))
                ->filter(fn ($id) => $id !== '')
                ->map(fn ($id) => Category::find((int) $id))
                ->filter()
                ->map(fn (Category $seriesCategory) => $seriesCategory->breadcrumbTrail());
        }

        return view('categories.series', compact(
            'seriesModel', 'categoryModel', 'items', 'randomFeatured',
            'mostDownloaded', 'mostRecent', 'categoryBreadcrumbTrail', 'seriesCategoryTrails'
        ));
    }
}
