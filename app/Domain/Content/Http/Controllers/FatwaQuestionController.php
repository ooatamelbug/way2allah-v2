<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Mail\FatwaFriendMail;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use App\Domain\Content\Models\FatwaQuestion;
use App\Domain\Content\Models\FatwaTopic;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Replaces `fatawa/single.php` ("one_question" op), `fatawa/answer.php` /
 * `fatawa/answer2.php` ("all_questions" op — confirmed identical query and
 * counter logic, `fatawa.md` §5; only markup differs), and
 * `fatawa/download.php` — Roadmap task 6.1, increment 1.
 *
 * `single.php` and `answer.php`/`answer2.php` target **different tables**,
 * confirmed by direct re-read while implementing (corrects an earlier
 * planning-stage assumption): `single.php`'s `$q` is a
 * `nuke_fatwa_questions.id` (one specific scholar's answer);
 * `answer.php`'s `$q` is a `nuke_fatwa_general_questions.id` (the shared
 * question, with all of its scholar answers listed together).
 */
class FatwaQuestionController
{
    /**
     * `single.php` — one specific scholar answer. **Confirmed: legacy
     * never increments any view counter on this page** (no `UPDATE`
     * targeting `num_view` anywhere in `single.php`, re-verified during
     * this implementation) — `recordView()` is deliberately NOT called
     * here, preserving that exact asymmetry with `showAll()` below.
     */
    public function show(int $question): View
    {
        $fatwaQuestion = FatwaQuestion::with(['author', 'channel'])->findOrFail($question);

        $topicModel = null;
        $categoryModel = null;

        // single.php:19-22 — topic_id is stored pipe-wrapped even on this
        // table's rows and is stripped before use.
        $topicId = (int) str_replace('|', '', (string) $fatwaQuestion->topic_id);
        if ($topicId > 0) {
            $topicModel = FatwaTopic::find($topicId);
            if ($topicModel !== null) {
                $categoryModel = Category::find($topicModel->parent_id);
            }
        }

        return view('fatawa.question-show', compact('fatwaQuestion', 'topicModel', 'categoryModel'));
    }

    /**
     * `answer.php`/`answer2.php` — all scholar answers for one general
     * question. Both legacy files share this exact query and the same
     * non-atomic `num_view` read-then-write (`fatawa.md` §5) —
     * modernized here to `RecordsView`'s atomic increment (decision-log
     * #9 precedent: not an observable behavior change).
     *
     * **OWNER-APPROVED CANONICAL PRESENTATION: `answer2.php`.** The prior
     * investigation ("`fatawa-all-{id}.htm` Reconstruction Report")
     * established `DISPATCH_ORIGIN_UNKNOWN` — `.htaccess:295` targets
     * `modules.php?op=all_questions`, and that dispatcher does not exist
     * anywhere in this legacy snapshot, so no source proves which of
     * `answer.php`/`answer2.php` legacy actually served at this URL. The
     * business owner explicitly selected `answer2.php`'s markup as the
     * migration's presentation reference (owner decision, not a
     * rediscovered historical fact — do not read this docblock as
     * `HISTORIC_HANDLER_PROVEN`). `fatawa.question-all` now reproduces
     * `answer2.php`'s DOM structure specifically.
     *
     * `page_bar($cat_id, $id, $q)` (`fatawa/functions.php:239-292`) is the
     * only chrome branch reachable through this route/controller contract
     * — `answer2.php`'s `auther_id`/`channel` GET-param branches
     * (`page_bar_auther()`/`page_bar_channels()`) have no equivalent
     * route parameter here and are UNREACHABLE_IN_CURRENT_MIGRATION, not
     * implemented. `$topicId`/`$categoryId` below replicate exactly what
     * `answer2.php:20-32` resolves from `$_GET['q']` before calling
     * `page_bar()` — same pattern `show()` already uses for `single.php`,
     * applied here to the *general* question's own `topic_id` instead of
     * one scholar answer's.
     */
    public function showAll(int $generalQuestion, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $generalQuestionModel = FatwaGeneralQuestion::findOrFail($generalQuestion);
        $answers = $listing->fatwaQuestionsForGeneralQuestion($generalQuestion);

        return $this->renderAllAnswers($generalQuestionModel, $answers, $sidebar);
    }

    /**
     * `auther-all-fatawa-{author}-{generalQuestion}.htm` — `BUSINESS_REPAIR`
     * (decision-log #51), explicitly NOT recovered legacy behavior.
     *
     * Full evidence trail (preserved, not rewritten — see decision-log
     * #48/#50/#51 for the complete history): `.htaccess:298` has a real
     * rule (`op=all_fatawa_for_auther`); `get_all_auther_questions()`
     * (`fatawa/functions.php:647`) is a real, live, currently-executing
     * generator of this exact URL; but no file among the 16 `fatawa/`
     * files ever reads `$_GET['g_q_id']`, and `modules.php` (the
     * dispatcher this op would have routed through) doesn't exist
     * anywhere, including on real production — classified
     * `LEGACY_BROKEN_LINK`. A follow-up data audit found ~7.5% of real
     * general questions have answers from more than one scholar (a
     * concrete example: general question 10007, "التصوير", has answers
     * from 9+ distinct scholars) — redirecting unconditionally to
     * `fatawa-all-{generalQuestion}.htm` would discard the author context
     * and show other scholars' answers in those cases. **Owner explicitly
     * selected author-scoped semantics**: same page, same presentation,
     * same everything `showAll()` above already does — the only
     * difference is `$answers` is additionally filtered to this author's
     * own rows via `ContentListingService::fatwaQuestionsForGeneralQuestion()`'s
     * new optional `$autherId` parameter (not a duplicated query).
     *
     * Invalid author or invalid general question: normal `findOrFail()`
     * 404s, same as `showAll()`. A valid author + valid general question
     * with no relationship between them (author never answered this
     * question) is NOT a 404 — both parent resources genuinely exist; the
     * page renders with an empty answers area, matching this project's
     * own established precedent (`get_all_auther_questions()` itself has
     * no invented "no results" message for an analogous empty case,
     * `auther-questions-*`'s own view) rather than fabricating fallback
     * content or a misleading 404.
     */
    public function showAllForAuthor(int $author, int $generalQuestion, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        Author::findOrFail($author);
        $generalQuestionModel = FatwaGeneralQuestion::findOrFail($generalQuestion);
        $answers = $listing->fatwaQuestionsForGeneralQuestion($generalQuestion, $author);

        return $this->renderAllAnswers($generalQuestionModel, $answers, $sidebar);
    }

    /**
     * Shared rendering path for `showAll()`/`showAllForAuthor()` — the
     * only difference between the two callers is whether `$answers` was
     * filtered by author before reaching here; every other decision
     * (view count, topic/category chain, channel lookup, sidebar) is
     * identical, so it lives in exactly one place.
     */
    private function renderAllAnswers(FatwaGeneralQuestion $generalQuestionModel, Collection $answers, ContentSidebarWidget $sidebar): View
    {
        $generalQuestionModel->recordView();

        $topicId = (int) str_replace('|', '', (string) $generalQuestionModel->topic_id);
        $topicModel = $topicId > 0 ? FatwaTopic::find($topicId) : null;
        $categoryId = $topicModel->parent_id ?? 0;
        $categoryChain = $categoryId > 0 ? $this->pageBarCategoryChain($categoryId) : collect();

        // answer2.php:100-103 — per-answer channel lookup for "مكان إصدار
        // الفتوى" ($place_of_fataw). ContentListingService::fatwaQuestionsForGeneralQuestion()
        // deliberately doesn't join this (shared by show() too, unchanged
        // here) — fetched once here as a small, page-specific, keyed
        // lookup rather than N+1 queries in the view or a service change.
        $channels = Channel::whereIn('id', $answers->pluck('channel_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $mostDownloaded = $categoryId > 0 ? $sidebar->fatwaMostDownloadedByCategory($categoryId) : collect();
        $mostRecent = $categoryId > 0 ? $sidebar->fatwaMostRecentByCategory($categoryId) : collect();

        return view('fatawa.question-all', compact(
            'generalQuestionModel', 'answers', 'topicModel', 'categoryId', 'categoryChain',
            'channels', 'mostDownloaded', 'mostRecent',
        ));
    }

    /**
     * `page_bar()`'s own bounded category-ancestor walk
     * (`fatawa/functions.php:250-261`) — a DIFFERENT, narrower mechanism
     * from `Category::breadcrumbTrail()` (the unbounded `main_cat` walk
     * backing the unrelated, shared `title()`/`breadcrumb()`/
     * `<x-page-chrome>` mechanism, deliberately NOT used on this page).
     * Ported literally rather than simplified: the starting category is
     * always included if it exists (title not required); up to 4 more
     * iterations walk `main_cat`, each appending only when the
     * newly-fetched row's title is non-empty (a title-less intermediate
     * is skipped from display but the walk still continues past it) — a
     * genuine legacy quirk (a deeply-nested category can silently lose
     * its top-most ancestors from this one breadcrumb), reproduced as
     * found. `krsort()` on legacy's sequentially-keyed array reverses
     * insertion order; `->reverse()` here is the same operation.
     *
     * @return Collection<int, Category>
     */
    private function pageBarCategoryChain(int $categoryId): Collection
    {
        $chain = collect();

        $current = Category::find($categoryId);
        if ($current === null) {
            return $chain;
        }

        $chain->push($current);

        for ($i = 0; $i < 4; $i++) {
            if ($current->main_cat == 0) {
                continue;
            }

            $next = Category::find($current->main_cat);
            if ($next === null) {
                break;
            }

            if ($next->title !== null && $next->title !== '') {
                $chain->push($next);
            }

            $current = $next;
        }

        return $chain->reverse()->values();
    }

    /**
     * `download.php` — atomic `num_download+1` (legacy already does this
     * correctly, no non-atomic pattern to modernize here) then a redirect
     * to the answer's `media_link`, matching legacy's `Header("Location:
     * ...")` exactly rather than streaming the file.
     *
     * **G-07-01 fix:** `download.php:13` runs `media_link` through
     * `fix_archive_links()` (`classes/archive.php`) before redirecting —
     * confirmed missing here (Phase 1 audit). Applied via `fixArchiveLinks()`
     * below, a verbatim port of the same function already ported once for
     * `BackupApiController::fixArchiveLinks()` — duplicated locally rather
     * than shared/extracted, since that controller belongs to an unrelated
     * feature and was out of this fix's approved scope.
     */
    public function download(int $question): RedirectResponse
    {
        $fatwaQuestion = FatwaQuestion::findOrFail($question);

        $fatwaQuestion->increment('num_download');

        return redirect()->away($this->fixArchiveLinks($fatwaQuestion->media_link ?? '') ?: '/');
    }

    /**
     * Verbatim port of `classes/archive.php`'s `fix_archive_links()` — see
     * `download()`'s docblock (G-07-01). Rewrites an archive.org URL to its
     * direct-download form; passes through unchanged for any other domain.
     */
    private function fixArchiveLinks(string $urlOld): string
    {
        $urlParts = explode('/', $urlOld);
        $domainParts = explode('.', $urlParts[2] ?? '');
        $last = strtolower((string) ($domainParts[count($domainParts) - 1] ?? ''));
        $secondLast = strtolower((string) ($domainParts[count($domainParts) - 2] ?? ''));

        if ($last === 'org' && $secondLast === 'archive') {
            $count = count($urlParts);

            return 'http://www.archive.org/download/'.($urlParts[$count - 2] ?? '').'/'.($urlParts[$count - 1] ?? '');
        }

        return $urlOld;
    }

    /**
     * `sendemail.php` (`op=sendemail`, `fatawa-friend-sendemail-{id}.htm`).
     * `$id` targets `nuke_fatwa_questions` (one specific answer), same
     * table as `show()`/`download()` above — confirmed via
     * `sendemail.php:47`'s `WHERE id='$id'`.
     *
     * **Validation reproduces legacy's own field rules exactly**
     * (`sendemail.php:13-36`): `your_name`/`friend_name` required, minimum
     * 2 characters; `your_email`/`friend_email` required, valid email
     * format. **One confirmed difference, flagged, not silently
     * reproduced:** legacy's hand-rolled `validEmail()` also performs a
     * live DNS `MX`/`A` record lookup on the email's domain
     * (`sendemail.php:160`) — not reproduced here. A DNS-dependent
     * validator is a materially different technical mechanism (a live
     * network call inside request validation, non-deterministic in
     * automated tests) that was not authorized as part of "preserve
     * behavior while using the approved mail architecture" — flagged as
     * an open technology question, not silently decided either way.
     *
     * Mail sending itself uses `FatwaFriendMail`/`Mail::send()`, not
     * legacy's raw `mail()` call — see that Mailable's own docblock for
     * exactly what content is preserved unchanged.
     */
    public function sendToFriend(int $question, Request $request): View|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'your_name' => ['required', 'string', 'min:2'],
            'your_email' => ['required', 'email'],
            'friend_name' => ['required', 'string', 'min:2'],
            'friend_email' => ['required', 'email'],
        ]);

        $fatwaQuestion = FatwaQuestion::with('author')->findOrFail($question);

        Mail::to($validated['friend_email'])->send(
            new FatwaFriendMail($fatwaQuestion, $validated['friend_name'], $validated['your_name'])
        );

        return view('fatawa.friend-sent');
    }
}
