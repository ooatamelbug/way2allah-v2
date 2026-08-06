<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['authorModel', 'mirrors.advanced'])
            ->findOrFail($khotab);

        $series = null;
        if ($khotabItem->ser_id > 0) {
            $series = Series::where('id', $khotabItem->ser_id)->where('hidden', 0)->first();
            abort_if($series === null, 404);
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

        // item.php:422 — unconditional `hits=hits+1, lastvisit=time()`,
        // fired regardless of admin/hidden status (there is no early
        // return before this line for a successfully-loaded item).
        $khotabItem->recordView();

        return view('khotab.item', compact('khotabItem', 'series', 'group', 'comments', 'randomFeatured', 'mostDownloaded', 'mostRecent'));
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
            return redirect('/' . $khotabItem->pdfPath());
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

    private function streamFile(?string $link): StreamedResponse
    {
        abort_if($link === null || $link === '' || ! is_file($link), 404, 'File not found');

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
