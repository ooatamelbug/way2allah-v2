<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Category;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `categories/category.php` — Roadmap task 4.3. Public-only,
 * same scope decision as every khotab controller (no admin-preview
 * branch — `category.php` itself has none anyway).
 *
 * Video only: both of `category.php`'s reachable branches set `$video =
 * 1` — the "audio" case is fully commented-out dead code, not a real
 * second mode. Confirmed by a full read of the file, not assumed.
 *
 * `op=var` (`ListVar()`, `var-category-{id}.htm`) is implemented below in
 * `showAnasheed()` — confirmed live in production (Evidence Reconciliation
 * pass), a separate method rather than a branch inside `show()` since it
 * targets a different content type/table (`nuke_anasheed_anasheed`, not
 * `nuke_islamic_khotab`) with its own distinct view.
 *
 * Deliberately NOT reproduced this pass (real, but not confirmed
 * reachable from this specific page — deferred, not silently dropped):
 * - `cat_id == 487`'s hardcoded special case (`ListMediaCoverage()`) — an
 *   unexplained magic category id with no evident business reason found
 *   in code; a Business Confirmation candidate, not implemented blind.
 * - `categories/functions.php`'s own `ListGroup()` — confirmed NOT
 *   called anywhere in `category.php` itself despite existing in the
 *   same file; presumably reachable from a categories page not yet
 *   located/read. `ContentListingService::groupsByCategory()` already
 *   exists from Wave 2 for whenever that call site is confirmed.
 *
 * `$author_id` (`category.php:25`, `$_GET['author']`) is read but
 * confirmed dead — `categories/functions.php`'s real `ListSeries()`/
 * `ListKhotab()` bodies accept an `$author_id` parameter but never
 * reference it in their SQL (same shape as w2acd's confirmed-dead
 * `$Group` parameter, P-016). `ContentListingService::seriesByCategoryAndGroup()`/
 * `khotabItemsByCategory()` (Wave 2) correctly never had this parameter
 * — confirmed correct by this session's re-reading, not a gap.
 */
class CategoryController
{
    public function show(int $category, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $categoryModel = Category::findOrFail($category);

        $series = $listing->seriesByCategoryAndGroup($category, 0, true);
        $items = $listing->khotabItemsByCategory($category, 0, 0, true);
        $mostDownloaded = $sidebar->khotabMostDownloadedByCategory($category);
        $mostRecent = $sidebar->khotabMostRecentByCategory($category);
        $randomFeatured = $sidebar->khotabRandomFeatured();

        // Shared Page Chrome Parity Audit: category.php:70-71's real
        // breadcrumb — 'التصنيفات الموضوعية' (linked to /categories.htm)
        // then Cat_Breadcrumb($cat_id)'s ancestor chain (ancestors linked,
        // current/leaf category plain — categories/functions.php:496-499's
        // $lastlink=0 default). The bare <nav><a> markup this replaced was
        // missing both the root label and the real page-breadcrumb DOM.
        $ancestorTrail = $categoryModel->breadcrumbTrail();
        $lastIndex = $ancestorTrail->count() - 1;
        $breadcrumbTrail = [
            ['title' => 'التصنيفات الموضوعية', 'url' => '/categories.htm'],
            ...$ancestorTrail->values()->map(fn (Category $c, int $i) => $i === $lastIndex
                ? ['title' => $c->title]
                : ['title' => $c->title, 'url' => '/category-'.$c->id.'.htm'])->all(),
        ];

        return view('categories.show', compact('categoryModel', 'series', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured', 'breadcrumbTrail'));
    }

    /**
     * `categories/category.php`'s `op=var` branch -> `ListVar()`.
     * Confirmed via direct reading: the sidebar ("اخترنا لك هذه المادة"/
     * "الأكثر تحميلا"/"جديد المواد") is rendered by the SAME unconditional
     * `randomitems()`/`topitems()` calls `show()` above uses — both
     * legacy functions hardcode `nuke_islamic_khotab` regardless of `$op`,
     * so this page's sidebar shows khotab content even though its main
     * listing is anasheed content. Reproduced exactly as found, not
     * "fixed" to show anasheed sidebar data instead.
     */
    public function showAnasheed(int $category, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $categoryModel = Category::findOrFail($category);

        $items = $listing->anasheedItemsByCategory($category);
        $mostDownloaded = $sidebar->khotabMostDownloadedByCategory($category);
        $mostRecent = $sidebar->khotabMostRecentByCategory($category);
        $randomFeatured = $sidebar->khotabRandomFeatured();
        $breadcrumbTrail = $categoryModel->breadcrumbTrail();

        return view('categories.show-anasheed', compact('categoryModel', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured', 'breadcrumbTrail'));
    }
}
