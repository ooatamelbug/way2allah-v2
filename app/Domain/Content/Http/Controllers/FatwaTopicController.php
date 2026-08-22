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
     *
     * **Full Design Parity Pass (2026-08-22) additions:**
     * - `under_this_tasnif()`'s real ordering is `ORDER BY level DESC, id
     *   DESC` then `krsort()` (array-position reversal) — net effect
     *   `level ASC, id ASC`, confirmed against a fresh live fetch
     *   (`fatawa/tobics.php?id=48`'s subcategory list, ascending ids).
     *   `children()` had no explicit order at all before this; added
     *   directly rather than via `Category::breadcrumbTrail()` (a
     *   different function, `Cat_Breadcrumb()`, unbounded depth — not
     *   reused, per the standing instruction not to conflate the two).
     * - `$ancestorChain` reproduces `page_bar()`'s own ancestor walk
     *   (`fatawa/functions.php:250-260`) exactly: up to 4 `main_cat`
     *   levels, self pushed first, each parent pushed only if its title
     *   is non-empty, final order root-first/self-last (legacy's own
     *   `krsort()` on a sequentially-keyed array). This is `page_bar()`'s
     *   own bounded-depth logic, not `Cat_Breadcrumb()`'s unbounded walk —
     *   built here, not on the `Category` model, since it belongs to this
     *   one Fatwa-specific chrome function alone.
     */
    public function show(int $category, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $categoryModel = Category::findOrFail($category);

        $subCategories = $categoryModel->children()
            ->where('q_count', '>', 0)
            ->orderBy('level')
            ->orderBy('id')
            ->get();
        $topics = $listing->fatwaTopicsByCategory($category, $page);
        $mostDownloaded = $sidebar->fatwaMostDownloadedByCategory($category);
        $mostRecent = $sidebar->fatwaMostRecentByCategory($category);
        $ancestorChain = $this->pageBarAncestorChain($categoryModel);
        $questionCounts = collect($topics->items())
            ->mapWithKeys(fn ($topic) => [$topic->id => $listing->fatwaGeneralQuestionCountForTopic($topic->id)]);

        return view('fatawa.topics-show', compact('categoryModel', 'subCategories', 'topics', 'mostDownloaded', 'mostRecent', 'ancestorChain', 'questionCounts'));
    }

    /** @return \Illuminate\Support\Collection<int, Category> */
    private function pageBarAncestorChain(Category $categoryModel): \Illuminate\Support\Collection
    {
        $chain = [$categoryModel];
        $current = $categoryModel;

        for ($i = 0; $i < 4; $i++) {
            if ($current->main_cat == 0) {
                continue;
            }

            $parent = Category::find($current->main_cat);

            if ($parent === null) {
                break;
            }

            if (! empty($parent->title)) {
                $chain[] = $parent;
            }

            $current = $parent;
        }

        return collect(array_reverse($chain));
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
     *
     * **Full Design Parity Pass (2026-08-22) additions — independently
     * verified against `subtobics.php`'s own source, NOT inferred from
     * `tobics.php`/`show()` above:**
     * - `page_bar($cat_id, $id)` — subtobics.php:34 passes the topic as
     *   the SECOND argument (`$tobic`), activating a branch `show()`
     *   above never exercises: one extra trailing breadcrumb entry
     *   ("موضوع {topic_name}", linking to this same page) appended after
     *   the same category ancestor chain `show()` already builds — same
     *   `pageBarAncestorChain()` helper, category-only, reused verbatim.
     * - Sidebars confirmed (not assumed) category-scoped, same as
     *   `show()`: `subtobics.php:104/117` call `mostdownload($cat_id)`/
     *   `recentlyadd($cat_id)`, the category id, not the topic id.
     * - `fatwaAnswerCountForGeneralQuestion()` reproduces `get_all_questions()`'s
     *   own per-row "عدد الفتاوى" badge (`functions.php:413-414`) — a
     *   different query shape from this page's own main list query
     *   (`fatwaGeneralQuestionsByTopic()`'s exact `topic_id='|id|'` match
     *   is the outer list; this is a join-free count of
     *   `nuke_fatwa_questions.general_question_id='|id|'` per row).
     */
    public function questions(int $topic, int $category, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $categoryModel = Category::findOrFail($category);
        $topicModel = FatwaTopic::findOrFail($topic);

        $generalQuestions = $listing->fatwaGeneralQuestionsByTopic($topic, $page);
        $answerCounts = collect($generalQuestions->items())
            ->mapWithKeys(fn ($question) => [$question->id => $listing->fatwaAnswerCountForGeneralQuestion($question->id)]);
        $mostDownloaded = $sidebar->fatwaMostDownloadedByCategory($category);
        $mostRecent = $sidebar->fatwaMostRecentByCategory($category);
        $ancestorChain = $this->pageBarAncestorChain($categoryModel);

        return view('fatawa.questions', compact('categoryModel', 'topicModel', 'generalQuestions', 'answerCounts', 'mostDownloaded', 'mostRecent', 'ancestorChain'));
    }
}
