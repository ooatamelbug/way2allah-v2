<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Mail\AnasheedFriendMail;
use App\Domain\Content\Models\AnasheedComment;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Replaces `anasheed/item.php` — Roadmap task 4.7. Public-only, same
 * scope decision as every other content controller — except `hidden` is
 * ALSO not filtered here, matching `anasheed/item.php`'s own confirmed
 * behavior exactly (a genuine legacy gap, not this controller's own
 * scope decision — see `AnasheedItem`'s docblock / IF-028).
 *
 * `download()` redirects to the stored `link` (a full external URL for
 * this module, confirmed by reading `download_anasheed()` — NOT streamed
 * via `fopen()` like `KhotabItemController::download()`).
 *
 * `anasheed_send_friend()` (`sendToFriend()` below) was deliberately NOT
 * ported in Wave 4 — deferred as an email side-feature, same treatment as
 * khotab's own still-unported `khotab_send_friend()`. **Implemented in
 * G-11-02 (Phase 1 audit)** — `send-friend-anasheed-{id}.htm` is a real
 * `.htaccess` rule with real, complete surviving source
 * (`anasheed/functions.php:647-677`), the same evidentiary standard that
 * already justified Fatawa's own send-to-friend port. `khotab`'s
 * equivalent remains deliberately deferred — not addressed by this
 * change, not silently expanded into scope.
 *
 * `download_var_group_getright()` (the `.grx` GetRight download-manager
 * feature) is now implemented — see `AnasheedGroupController::
 * downloadGetright()`, since it lives in `anasheed/group.php`, not this
 * controller's own `anasheed/item.php`.
 */
class AnasheedItemController
{
    /**
     * G-12-01 (G-12 investigation): `var-item-{id}-page-{page}.htm` is a
     * real, live `.htaccess` rule (`.htaccess:104`) with real
     * pagination-link generation (`anasheed/functions.php:833-836`'s
     * `PS_Pagination::renderFullNav()`) — confirmed reachable, not dead.
     *
     * **Deliberately NOT reproduced:** `item.php:25-30` decrements `$page`
     * *twice* (once at line 26, again at line 30 if still `>0`) before
     * using it as the pagination offset — a confirmed legacy bug that
     * makes every requested page N≥2 actually render page N-1's comments.
     * Per the project's Behavior-First-but-not-bug-for-bug precedent
     * (decision-log — G-10-02's `RecheckTelawahLinkSizeJob` fix; G-10-01's
     * explicit instruction not to reproduce a confirmed legacy miscount),
     * the captured `{page}` route segment is passed straight into
     * `paginate()`'s page argument with no adjustment — `page=2` correctly
     * shows comments 21-40, not comments 1-20. `$page` is nullable so the
     * un-paginated `/var-item-{id}.htm` route (no `{page}` segment) keeps
     * its existing, unchanged behavior of auto-resolving from a `?page=`
     * query string.
     */
    /**
     * var-item-17350.htm parity: `item.php:6,31-32`'s `$title` — starts
     * `""`, then `.= ' - ' . $Group->title`, then `.= ' - ' . $Anasheed->title`
     * — used BOTH for the `<title>` (`$header['title']` = `$title.' -
     * '.$sitename`, then `w2a_header()`'s own template appends `' - '
     * .$sitename` a SECOND time — a confirmed, genuine double-suffix,
     * verified against live production, not a fetch artifact) and for the
     * `<h3 class="page-title">` heading via `title($title)` (`functions.php:541-543`
     * — its own malformed `<i class=\fa fa-gift\">` icon already established
     * as SOURCE_UNRECOVERABLE/deliberately dropped elsewhere in this
     * project, applied consistently here, not re-decided).
     */
    public function show(int $anasheed, ContentSidebarWidget $sidebar, ?int $page = null): View
    {
        $anasheedItem = AnasheedItem::with(['mirrors.advanced', 'group'])->findOrFail($anasheed);

        $pageTitle = $anasheedItem->group
            ? ' - '.$anasheedItem->group->title.' - '.$anasheedItem->title
            : ' - '.$anasheedItem->title;

        $breadcrumbTrail = $anasheedItem->group?->breadcrumbTrail() ?? collect();

        $comments = null;
        if ($anasheedItem->comments > 0) {
            $comments = $anasheedItem->comments()->where('view', 1)->orderByDesc('id')->paginate(20, ['*'], 'page', $page);
        }

        $mostDownloaded = $sidebar->anasheedMostDownloaded($anasheedItem->group_id);
        $mostRecent = $sidebar->anasheedMostRecent($anasheedItem->group_id);

        $anasheedItem->recordView();

        return view('anasheed.item', compact('anasheedItem', 'pageTitle', 'breadcrumbTrail', 'comments', 'mostDownloaded', 'mostRecent'));
    }

    public function download(int $anasheed): \Illuminate\Http\RedirectResponse
    {
        $anasheedItem = AnasheedItem::select(['id', 'link'])->findOrFail($anasheed);

        $anasheedItem->incrementDownloadCount();

        return redirect(str_replace('http:', 'https:', $anasheedItem->link));
    }

    public function downloadMirror(int $anasheed, int $mirror): \Illuminate\Http\RedirectResponse
    {
        $mirrorModel = AnasheedMirror::where('khid', $anasheed)->findOrFail($mirror);

        $mirrorModel->incrementDownloadCount();

        return redirect(str_replace('http:', 'https:', (string) $mirrorModel->link));
    }

    /**
     * Post-Wave-4 cross-wave review fixes (decision-log #7): `code` is now
     * resolved via `GeoIpLookup` instead of hardcoded `''`, and
     * `CommentPosted` is dispatched after the insert — see that event's
     * own docblock for why no listener is registered yet.
     */
    public function storeComment(int $anasheed, Request $request, GeoIpLookup $geoIp): string
    {
        $nickname = trim((string) $request->input('user_nickname'));
        $body = trim((string) $request->input('user_comment'));

        if ($nickname === '') {
            return '2';
        }

        if ($body === '') {
            return '3';
        }

        $comment = AnasheedComment::create([
            'khid' => $anasheed,
            'uid' => 0,
            'uname' => $nickname,
            'ip' => $request->ip() ?? '',
            'code' => $geoIp->codeForIp($request->ip() ?? ''),
            'mytime' => now()->timestamp,
            'comment' => $body,
            'view' => 0,
        ]);

        CommentPosted::dispatch($comment);

        return '1';
    }

    /**
     * `anasheed_send_friend()` (`anasheed/functions.php:647-677`,
     * `op=send_friend`). **Reproduces legacy's own bare-numeric-code
     * response shape exactly** (`echo 2; die();` on any validation
     * failure, `echo 1; die();` on success) — matching this same
     * controller's own already-established `storeComment()` convention,
     * not a Blade-view-with-session-errors shape like `FatwaQuestionController::
     * sendToFriend()`'s (a genuinely different legacy response mechanism,
     * not something to harmonize across modules).
     *
     * **Validation confirmed different from Fatawa's own precedent, not
     * copied from it:** all 4 fields required + both emails must pass
     * `FILTER_VALIDATE_EMAIL` — legacy has no minimum-length name check
     * (Fatawa's `sendemail.php` does, 2 characters). One combined
     * condition, not per-field messages — legacy's own `echo 2` fires
     * identically for every failure reason, reproduced as one check here.
     *
     * **Legacy's own DNS-lookup email validator does NOT exist in this
     * module** (`filter_var(..., FILTER_VALIDATE_EMAIL)` is a pure format
     * check, unlike Fatawa's `sendemail.php`'s live MX/A lookup) — nothing
     * deferred here, this module's own legacy validation is already
     * network-free.
     *
     * **Not reproduced:** legacy's `echo -1` mail-send-failure response
     * (`shams_mail_no_spam()`'s own return-value check) — Laravel's
     * `Mail` facade does not expose an equivalent synchronous
     * success/failure boolean the way raw `mail()` does; a failed send
     * throws instead. A technology-shape difference, not a behavior
     * invention.
     */
    public function sendToFriend(int $anasheed, Request $request): string
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

        $anasheedItem = AnasheedItem::with('group')->findOrFail($anasheed);

        Mail::to($friendEmail)->send(
            new AnasheedFriendMail($anasheedItem, $friendName, $yourName, $yourEmail)
        );

        return '1';
    }
}
