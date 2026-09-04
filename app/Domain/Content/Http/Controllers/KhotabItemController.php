<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Mail\KhotabFriendMail;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Services\GeoIpLookup;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replaces `khotab/item.php` (Roadmap task 4.1) — the single richest legacy
 * source file in the audit for this module. Public-only for now: legacy's
 * `$hiddenSql`/`UserType()` admin-preview-of-hidden-content branch is
 * deliberately NOT reproduced here, matching the scope precedent already
 * set by `ChannelController`/`LiveStreamController` (neither wires in any
 * admin guard either) and the Blueprint's own open question #6
 * ("khotab/telawah admin content-CRUD — design fresh... or handled some
 * other way?", `00-master-migration-blueprint.md`) — admin-only behavior
 * for this module is unresolved scope, not silently decided here. Every
 * public-facing query below always applies `hidden = 0`.
 *
 * `khotab_send_friend()`/`send_friend_modal()` are NOT ported in this pass
 * (an email-sending side feature, independent of the core detail/download/
 * comment behavior) — tracked as deferred scope in the Wave 4 report, not
 * a silent gap.
 *
 * **Missing-series repair (decision-log #55), `MIGRATION_STRICTNESS_DEFECT`,
 * owner-approved, explicitly NOT legacy parity.** `show()`'s series lookup
 * previously `abort_if($series === null, 404)` whenever `ser_id > 0`
 * resolved to no row (or a hidden one) — a real DB investigation found
 * 1,349 visible items across 91 distinct genuinely-missing series ids,
 * whose item content (title, author, description, media/download links,
 * mirrors, comments, all sidebar widgets) is entirely independent of the
 * series — `khotab.item.blade.php` reads `$series` in exactly one place
 * (the optional breadcrumb crumb, already `@if ($series)`-guarded).
 * Legacy's own real behavior for this exact condition is not a 404 either
 * — `item.php:62-68` prints the page header/chrome then `return`s,
 * producing a broken, un-footered `200` with an empty content area — not
 * a standard worth reproducing. Repaired to distinguish the 2 conditions
 * a single `->where('hidden', 0)->first()` call could not tell apart: a
 * genuinely nonexistent series row now renders the item normally with
 * `$series = null`; a series row that exists but is hidden was, at the
 * time, deliberately left 404ing pending its own separate owner review.
 *
 * **Hidden-series repair (decision-log #56), `BUSINESS_CONFIRMATION_REQUIRED`
 * → owner-approved as `ITEM_VISIBILITY_IS_INDEPENDENT` (candidate B),
 * explicitly NOT legacy parity.** A dedicated investigation found 834
 * visible items referencing 61 hidden series — 97.3% of all children of
 * hidden series remain `item.hidden = 0`; nothing in legacy ever cascaded
 * `series.hidden` into child `hidden` values; legacy's own public
 * generators (`ListPDF()`/its Laravel port `khotabPdfItemsByAuthor()`)
 * already list these items regardless of parent visibility. Owner decided
 * `item.hidden` is the authoritative item-level visibility signal: a
 * hidden series now resolves to `$series = null` exactly like a
 * nonexistent one (merged into the same branch above). `KhotabSeriesController`
 * is deliberately untouched — a hidden series' own `/khotab-series-{id}.htm`
 * page still 404s; these 2 semantics are kept separate on purpose, per
 * explicit instruction.
 */
class KhotabItemController
{
    /**
     * Fixes IF-014 (item.php:467,476's `$Khotab->video` typo — this
     * controller uses the item's real `vedio` value) and IF-019 (broken
     * comment flag path) and IF-020 (broken PDF-download link) via the
     * `khotab.item` view. Comments are gated by the stored `comments`
     * counter exactly as legacy does (`item.php:366`), then filtered to
     * `view = 1` inline (see `Comment`'s own docblock for the
     * moderation-gate business rule this preserves).
     */
    public function show(int $khotab, ContentSidebarWidget $sidebar): View
    {
        $khotabItem = KhotabItem::where('hidden', 0)
            ->with(['authorModel', 'mirrors.advanced', 'categories'])
            ->findOrFail($khotab);

        $series = null;
        if ($khotabItem->ser_id > 0) {
            $seriesRow = Series::find($khotabItem->ser_id);

            // decision-log #56: owner-approved — a hidden series resolves
            // to null exactly like a nonexistent one (item.hidden is the
            // authoritative item-level visibility flag; series.hidden
            // never cascaded to child items in legacy either). The view's
            // own `@if ($series)` guard already omits just the series
            // breadcrumb crumb — no hidden series title/id is exposed
            // anywhere. `KhotabSeriesController` is deliberately untouched:
            // the hidden series' own `/khotab-series-{id}.htm` page stays
            // 404, per explicit instruction to keep these 2 semantics
            // separate.
            if ($seriesRow !== null && (int) $seriesRow->hidden === 0) {
                $series = $seriesRow;
            }
        }

        $group = null;
        if ($khotabItem->group_id > 0) {
            $group = KhotabGroup::where('id', $khotabItem->group_id)->where('hidden', 0)->first();
            abort_if($group === null, 404);
        }

        $comments = null;
        if ($khotabItem->comments > 0) {
            $comments = $khotabItem->comments()->where('view', 1)->orderByDesc('id')->paginate(10, ['*'], 'page');
        }

        $video = (bool) $khotabItem->vedio;

        $randomFeatured = $sidebar->khotabRandomFeatured();
        $mostDownloaded = $sidebar->khotabMostDownloadedByVideoFlag($video);
        $mostRecent = $sidebar->khotabMostRecentByVideoFlag($video);

        $categoryChains = $this->categoryBreadcrumbChains($khotabItem);

        // item.php:422 — unconditional `hits=hits+1, lastvisit=time()`,
        // fired regardless of admin/hidden status (there is no early
        // return before this line for a successfully-loaded item).
        $khotabItem->recordView();

        return view('khotab.item', compact('khotabItem', 'series', 'group', 'comments', 'randomFeatured', 'mostDownloaded', 'mostRecent', 'categoryChains'));
    }

    /**
     * Visual parity audit (khotab-item-298784.htm, 2026-08-18):
     * `item.php:123`'s `breadcrumb($breadcrumb, 1, true, $Khotab->cat)` —
     * the `$categories` argument triggers `functions.php:475-506`'s
     * category-tree extension rendered inside `.page-bar`. That legacy
     * code parses the raw `cat` pipe-string (a confirmed data-quality
     * anti-pattern per `KhotabItem`'s own docblock); this uses the
     * already-established `categories()` junction-table relationship and
     * `Category::breadcrumbTrail()` (already ancestors-first, matching
     * `breadcrumb()`'s own `while ($Cat->main_cat > 0)` walk exactly —
     * confirmed live for this item's single category: 513, parent 240)
     * instead of duplicating that walk here. Rendering one chain per
     * category — not just the last, unlike legacy's own `$FullCats = `
     * (not `.=`) overwrite bug — is untested against a real
     * multi-category item (this one only has one) but is the more
     * correct behavior for the properly normalized relationship; the bug
     * was an artifact of the old buggy single-string representation, not
     * a rule worth preserving.
     *
     * @return array<int, Collection<int, Category>>
     */
    private function categoryBreadcrumbChains(KhotabItem $khotabItem): array
    {
        return $khotabItem->categories
            ->map(fn (Category $leaf) => $leaf->breadcrumbTrail())
            ->all();
    }

    /**
     * `khotab/functions.php:957-991`'s `download_khotab()`, primary-link
     * branch — deliberately NO `hidden` filter (legacy's own query has
     * none either: direct-download-by-id works even for a hidden item,
     * preserved as found rather than "fixed").
     */
    public function download(int $khotab): StreamedResponse
    {
        $khotabItem = KhotabItem::select(['id', 'link', 'linksize'])->findOrFail($khotab);

        $khotabItem->incrementDownloadCount();

        return $this->streamFile($khotabItem->link);
    }

    /** `khotab/functions.php:957-991`'s `download_khotab()`, mirror branch — increments the mirror's own `hits`, not the item's `downcount`. */
    public function downloadMirror(int $khotab, int $mirror): StreamedResponse
    {
        $mirrorModel = Mirror::where('khid', $khotab)->findOrFail($mirror);

        $mirrorModel->incrementDownloadCount();

        return $this->streamFile($mirrorModel->link);
    }

    /**
     * `khotab/functions.php:1048-1059`'s `download_khotab_pdf()` — a
     * redirect to the bucketed PDF path (`MediaPathResolver`), not a
     * streamed response, matching legacy's own `Header("Location: ...")`
     * exactly. This is the fix for IF-020 (`item.php`'s dead
     * `khotab-item-pdf-{id}.htm` link) — the Laravel route backing this
     * action is what that button should have pointed at.
     */
    public function downloadPdf(int $khotab): RedirectResponse
    {
        $khotabItem = KhotabItem::select(['id', 'pdf'])->findOrFail($khotab);

        if ($khotabItem->pdf > 0) {
            return redirect(MediaUrl::asset($khotabItem->pdfPath()));
        }

        return redirect('/');
    }

    /**
     * `khotab/functions.php:1094-1144`'s `add_khotab_comment()`. Returns
     * the same bare status-code-as-body contract legacy used
     * (`echo 1;`/`echo 2;`/`echo 3;`/`echo -1;`) since this is called from
     * `post_comment_modal()`'s AJAX form — changing the response shape
     * without also changing the untouched legacy JS that reads it would
     * break the feature, not improve it.
     *
     * Post-Wave-4 cross-wave review fixes (decision-log #7): `code` is now
     * resolved via `GeoIpLookup` instead of hardcoded `''` (Blueprint §4
     * names this exact consumer), and `CommentPosted` is dispatched after
     * the insert (Blueprint §5) — see that event's own docblock for why no
     * listener is registered yet.
     */
    public function storeComment(int $khotab, Request $request, GeoIpLookup $geoIp): string
    {
        $nickname = trim((string) $request->input('user_nickname'));
        $body = trim((string) $request->input('user_comment'));

        if ($nickname === '') {
            return '2';
        }

        if ($body === '') {
            return '3';
        }

        $comment = Comment::create([
            'khid' => $khotab,
            'uid' => 0,
            'uname' => $nickname,
            'ip' => $request->ip() ?? '',
            'code' => $geoIp->codeForIp($request->ip() ?? ''),
            'mytime' => now()->timestamp,
            'comment' => $body,
            'uemail' => '',
            'view' => 0,
        ]);

        CommentPosted::dispatch($comment);

        return '1';
    }

    /**
     * Visual parity audit (khotab-item-298784.htm, 2026-08-18) Batch 3 /
     * Finding #11: `khotab_send_friend()` (`khotab/functions.php:1202-1230`).
     * Reproduces legacy's own bare-numeric-code response shape exactly
     * (`echo 2; die();` on validation failure, `echo 1; die();` on
     * success), matching this same controller's own `storeComment()`
     * convention and `AnasheedItemController::sendToFriend()`'s
     * established precedent for the same underlying
     * `shams_mail_no_spam()` helper. Validation: all 4 fields required +
     * both emails must pass `FILTER_VALIDATE_EMAIL` — one combined check,
     * matching legacy's own single `echo 2` branch for every failure
     * reason (confirmed identical to `AnasheedItemController::
     * sendToFriend()`'s own validation, not copied without checking).
     * `hidden = 0` filter applied for consistency with this controller's
     * own standing "every public-facing query applies hidden = 0" policy
     * (class docblock) — legacy's own `get_khotab()` has no such filter,
     * but that's this controller's already-established convention, not a
     * new decision made here. See `KhotabFriendMail`'s own docblock for
     * the one deliberate deviation from legacy (the item link).
     */
    public function sendToFriend(int $khotab, Request $request): string
    {
        $yourName = trim((string) $request->input('your_name'));
        $yourEmail = trim((string) $request->input('your_email'));
        $friendName = trim((string) $request->input('friend_name'));
        $friendEmail = trim((string) $request->input('friend_email'));

        if ($yourName === '' || $friendName === ''
            || ! filter_var($yourEmail, FILTER_VALIDATE_EMAIL)
            || ! filter_var($friendEmail, FILTER_VALIDATE_EMAIL)
        ) {
            return '2';
        }

        $khotabItem = KhotabItem::where('hidden', 0)->findOrFail($khotab);

        Mail::to($friendEmail)->send(
            new KhotabFriendMail($khotabItem, $friendName, $yourName, $yourEmail)
        );

        return '1';
    }

    /**
     * G-01 fix (Migration Gap Register): legacy `download_khotab()`
     * (`khotab/functions.php:993-1032`) never checks the link exists
     * before opening it — it calls `@fopen($_link, 'rb')` unconditionally
     * for any non-empty link, local or remote (PHP's `fopen()` supports
     * the `http(s)://` stream wrapper natively). This method's own prior
     * `is_file()` pre-check has no legacy counterpart and rejects every
     * `http(s)://` link before `fopen()` is ever attempted — confirmed
     * against real data to affect 99.9% of khotab items and mirrors.
     *
     * Fixed narrowly: `is_file()` is skipped only for `http://`/`https://`
     * links, matching legacy's own effective behavior for the case that
     * was actually broken. It is still applied for every other non-empty
     * link (local paths, garbled/placeholder strings) — that class of
     * link already 404s today via `is_file()` returning false, and this
     * fix does not change that outcome; only the http(s) case changes.
     */
    private function streamFile(?string $link): StreamedResponse
    {
        abort_if($link === null || $link === '', 404, 'File not found');

        $isRemote = str_starts_with($link, 'http://') || str_starts_with($link, 'https://');

        abort_if(! $isRemote && ! is_file($link), 404, 'File not found');

        return response()->streamDownload(function () use ($link) {
            $handle = fopen($link, 'rb');
            abort_if($handle === false, 500, 'Error reading file');

            while (! feof($handle)) {
                echo fread($handle, 8192);
            }

            fclose($handle);
        }, basename($link));
    }
}
