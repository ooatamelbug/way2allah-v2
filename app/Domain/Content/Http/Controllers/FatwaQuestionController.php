<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Mail\FatwaFriendMail;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\FatwaGeneralQuestion;
use App\Domain\Content\Models\FatwaQuestion;
use App\Domain\Content\Models\FatwaTopic;
use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * **Layout deliberately left unresolved** (per the approved technical
     * plan, §3.4/§7): `answer.php` and `answer2.php` differ only in
     * markup/CSS/element ordering, and which of the two the business
     * wants has not been decided. The view backing this action
     * (`fatawa.question-all`) renders the same data neutrally — using
     * neither file's specific CSS class scheme nor its specific
     * action-icon/table ordering — rather than silently picking one.
     */
    public function showAll(int $generalQuestion, ContentListingService $listing): View
    {
        $generalQuestionModel = FatwaGeneralQuestion::findOrFail($generalQuestion);
        $answers = $listing->fatwaQuestionsForGeneralQuestion($generalQuestion);

        $generalQuestionModel->recordView();

        return view('fatawa.question-all', compact('generalQuestionModel', 'answers'));
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
