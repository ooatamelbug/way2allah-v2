<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\FatwaTopic;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/fatawa.php` (`showtree()`), `fatawa/tobics.php`
 * (`get_all_tasnifat()`), `fatawa/subtobics.php` (`get_all_questions()`)
 * — Roadmap task 6.1.
 *
 * Category tree root uses the existing `Category` model (`nuke_w2a_cat`)
 * directly, unchanged — the same table/model `khotab`/`categories`
 * already share. No new model for this level (confirmed reuse, per the
 * approved technical plan).
 *
 * **`showtree()`'s presentation, explicitly assessed (2026-08-10, before
 * increment 2), not silently declared equivalent:** `fatawa.php:44-145`
 * read in full, including `have_sub()` (`fatawa.php:34-40`). The nested
 * checkbox/accordion structure is confirmed to be **pure CSS presentation**
 * — a `<input type="checkbox" hidden>` + `<label>` expand/collapse trick,
 * no JavaScript, no server round-trip. Critically: **every single node at
 * every nesting level — whether it "has sub" (renders the accordion
 * toggle) or is a leaf — always renders a real `<a href="fatawa-topics-{id}.htm">`
 * link to the exact same drill-down target this controller's `show()`
 * action serves** (`fatawa.php:70`, `:78`, `:99`, `:107`, `:122` — five
 * separate link sites, all the same URL shape, all reachable regardless
 * of the accordion state). No access control, no data-shape difference,
 * and no navigable-destination difference exists between "expand this
 * node in place" and "navigate to this node's own page" — confirmed by
 * reading every branch, not inferred. On this evidence, the single-page
 * vs. drill-down choice **is** presentation technology, not a business
 * behavior requirement, for the *reachability* question specifically.
 * **What is NOT settled by this evidence, and is flagged rather than
 * decided:** whether the business specifically wants the "see the whole
 * tree at once, no page reloads" experience for its own sake (a UX
 * preference `showtree()`'s existence doesn't prove either way) — that is
 * a genuine open presentation question, not resolved here. Separately,
 * `showtree()`'s 3 nested loops hard-code exactly 3 tree levels (root →
 * sub → sub-sub) — deeper nesting, if any exists in the data, would be
 * silently invisible in legacy but *is* reachable via this controller's
 * recursive `show()`, since each sub-category's own page calls `show()`
 * again. Noted as a difference, not claimed as an intentional
 * improvement decided here.
 */
class FatwaTopicController
{
    /**
     * `fatawa.php`'s `showtree()` — top-level categories only (`main_cat=0`), same `q_count>0` filter. No `.htaccess` page parameter exists for `fatawa.htm` — none added here.
     *
     * **G-07-02 fix:** the page's two sidebar boxes (`tasnifat_latestadd()`/
     * `tasnifat_active()`, `fatawa.php:147-161`) are NOT scoped to
     * `main_cat=0` in legacy — they query the whole `nuke_w2a_cat` table.
     * Phase 1 audit found the prior view-level approximation (re-sorting
     * this same `main_cat=0` `$categories` collection) was 0/10 correct
     * against legacy's real "latest added" top-10. Now sourced from
     * `ContentSidebarWidget::fatwaLatestAddedCategories()`/
     * `fatwaMostActiveCategories()`, dedicated unfiltered queries.
     */
    public function index(ContentSidebarWidget $sidebar): View
    {
        $categories = Category::where('main_cat', 0)
            ->where('q_count', '>', 0)
            ->get();

        $latestAddedCategories = $sidebar->fatwaLatestAddedCategories();
        $mostActiveCategories = $sidebar->fatwaMostActiveCategories();

        return view('fatawa.topics-index', compact('categories', 'latestAddedCategories', 'mostActiveCategories'));
    }

    /**
     * `tobics.php` — one category's fatwa topics (`ContentListingService::fatwaTopicsByCategory()`)
     * plus its own sub-categories (`under_this_tasnif()`'s `nuke_w2a_cat`
     * `main_cat=$id AND q_count>0` query — the same relation `Category`
     * already exposes via `children()`, filtered here to match legacy's
     * `q_count>0` condition). Route: `.htaccess:276` confirmed, literal
     * text re-read — `^fatawa-topics-([0-9]*)-([0-9]*).htm ...&cat_id=$1&page=$2`
     * — no 1-parameter variant exists at all; `$page` is a real, required
     * part of this URL, not optional.
     */
    public function show(int $category, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $categoryModel = Category::findOrFail($category);

        $subCategories = $categoryModel->children()->where('q_count', '>', 0)->get();
        $topics = $listing->fatwaTopicsByCategory($category, $page);
        $mostDownloaded = $sidebar->fatwaMostDownloadedByCategory($category);
        $mostRecent = $sidebar->fatwaMostRecentByCategory($category);

        return view('fatawa.topics-show', compact('categoryModel', 'subCategories', 'topics', 'mostDownloaded', 'mostRecent'));
    }

    /**
     * `subtobics.php` — general questions under one topic
     * (`ContentListingService::fatwaGeneralQuestionsByTopic()`), with the
     * owning category for breadcrumb context.
     *
     * **Route parameter order corrected (2026-08-10) — the increment 1
     * version of this route had it backwards.** Literal `.htaccess` text
     * re-read directly (`:301-302`): `^fatawa-group-([0-9]*)-([0-9]*)(-([0-9]*))?.htm
     * ...&t_id=$1&cat_id=$2&page=$3` — the URL order is **topic id
     * first, category id second** (`t_id=$1`), the opposite of what was
     * shipped in increment 1. Two real rules exist: a 2-parameter form
     * (page defaults to 1) and a 3-parameter form with an explicit page —
     * both registered in `routes/content.php`.
     */
    public function questions(int $topic, int $category, int $page, ContentListingService $listing): View
    {
        $categoryModel = Category::findOrFail($category);
        $topicModel = FatwaTopic::findOrFail($topic);

        $generalQuestions = $listing->fatwaGeneralQuestionsByTopic($topic, $page);

        return view('fatawa.questions', compact('categoryModel', 'topicModel', 'generalQuestions'));
    }
}
