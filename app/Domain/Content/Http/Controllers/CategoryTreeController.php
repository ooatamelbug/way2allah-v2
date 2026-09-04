<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;

/**
 * Replaces `categories/tree.php` — the category tree/index page
 * (`categories.htm`), previously deferred (`CategoryController`'s own
 * earlier docblock: "category tree/index page… NOT yet ported"). Legacy
 * evidence: `.htaccess:172` (`categories.htm -> categories/tree.php`, a
 * real file, not a dead dispatcher) and `nuke_w2a_cat` confirmed present
 * with real data (531 rows with `video_count > 0`, 46 top-level) —
 * Evidence Reconciliation pass.
 *
 * `op`-less/default branch (`$tb_field='video_count'`, `$slug='category-'`)
 * -> `index()`. `op=var` branch (`$tb_field='anasheed_count'`,
 * `$slug='var-category-'`, `var-categories.htm`) -> `varIndex()` below —
 * every link it produces resolves to the already-implemented
 * `var-category-{id}.htm` (`CategoryController::showAnasheed()`).
 *
 * `op=fatawa` branch (`fatawa-categories.htm`) -> `fatawaIndex()` below.
 * The tree page's OWN legacy source is complete and fully understood (the
 * same shared `showtree()` already ported twice above) — implemented here
 * on that basis. It is deliberately NOT the same thing as
 * `fatawa-category-{id}.htm` (the per-category detail page every tree
 * node links to): that page's own legacy source (`fatawa/category.php`)
 * is confirmed genuinely unrecoverable (IF-038, Fatawa Categories Source
 * Recovery pass — checked git history, exhaustive codebase search,
 * structural comparison against both existing `category.php` files; none
 * recovered it). `fatawa-category-{id}.htm` is intentionally NOT built
 * and NOT redirected anywhere — inventing its behavior, or silently
 * routing it to the analogous-but-unproven `fatawa-topics-{id}-{page}.htm`
 * capability, would both be new behavioral decisions this evidence does
 * not authorize. The tree page's generated `fatawa-category-{id}.htm`
 * links are expected to not resolve — this is a known, documented,
 * separate open item, not a defect in this implementation.
 *
 * Separate controller from `CategoryController` (which replaces the
 * different legacy file `categories/category.php`) — same "one controller
 * per legacy file" convention already used elsewhere in this domain.
 */
class CategoryTreeController
{
    public function index(ContentListingService $listing): View
    {
        return view('categories.tree', [
            'categoriesByParent' => $listing->categoryTree()->groupBy('main_cat'),
        ]);
    }

    public function varIndex(ContentListingService $listing): View
    {
        return view('categories.tree-anasheed', [
            'categoriesByParent' => $listing->anasheedCategoryTree()->groupBy('main_cat'),
        ]);
    }

    public function fatawaIndex(ContentListingService $listing): View
    {
        return view('categories.tree-fatawa', [
            'categoriesByParent' => $listing->fatawaCategoryTree()->groupBy('main_cat'),
        ]);
    }
}
