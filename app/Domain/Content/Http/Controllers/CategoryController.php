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
 * Deliberately NOT reproduced this pass (real, but not confirmed
 * reachable from this specific page — deferred, not silently dropped):
 * - `op=var` (`ListVar()`) — a different content type (`vars` module,
 *   its own separate Blueprint-owned module), not part of this session's
 *   khotab-focused model graph.
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
        $breadcrumbTrail = $categoryModel->breadcrumbTrail();

        return view('categories.show', compact('categoryModel', 'series', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured', 'breadcrumbTrail'));
    }
}
