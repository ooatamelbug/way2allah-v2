<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Events\CommentPosted;
use App\Domain\Content\Models\AnasheedComment;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
 * `khotab_send_friend()`'s anasheed equivalent (`anasheed_send_friend()`)
 * and the `.grx` GetRight download-manager feature
 * (`download_var_group_getright()`) are deliberately NOT ported this
 * pass — an email side-feature and a download-accelerator format that
 * predate this deployment's likely actual usage, same deferred-scope
 * treatment as khotab's own `khotab_send_friend()`.
 */
class AnasheedItemController
{
    public function show(int $anasheed, ContentSidebarWidget $sidebar): View
    {
        $anasheedItem = AnasheedItem::with('mirrors.advanced')->findOrFail($anasheed);

        $comments = null;
        if ($anasheedItem->comments > 0) {
            $comments = $anasheedItem->comments()->where('view', 1)->orderByDesc('id')->paginate(20, ['*'], 'page');
        }

        $mostDownloaded = $sidebar->anasheedMostDownloaded($anasheedItem->group_id);
        $mostRecent = $sidebar->anasheedMostRecent($anasheedItem->group_id);

        $anasheedItem->recordView();

        return view('anasheed.item', compact('anasheedItem', 'comments', 'mostDownloaded', 'mostRecent'));
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
}
