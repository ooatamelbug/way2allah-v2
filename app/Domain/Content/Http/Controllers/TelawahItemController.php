<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\TelawahItem;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Support\Downloads\DownloadRejectionContext;
use App\Support\Downloads\ProxiedDownloadResponder;
use App\Support\Downloads\Telemetry\DownloadTelemetryContext;
use App\Support\Media\ArchiveOrgLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces `telawah/item.php` — Roadmap task 4.8.
 *
 * `show()` deliberately does NOT increment `hits` — see `TelawahItem`'s
 * docblock; legacy's own `item.php`/`draw_telawah()` never do either.
 *
 * `download()` redirects directly to `link` (no `http:`→`https:` rewrite,
 * unlike `AnasheedItemController::download()` — confirmed by direct
 * reading of `download_telawah()`, which has no such replacement).
 */
class TelawahItemController
{
    public function show(int $telawah, ContentSidebarWidget $sidebar): View
    {
        $telawahItem = TelawahItem::findOrFail($telawah);

        $mostDownloaded = $sidebar->telawahMostDownloaded();
        $mostRecent = $sidebar->telawahMostRecent();

        return view('telawah.item', compact('telawahItem', 'mostDownloaded', 'mostRecent'));
    }

    /** F04 (owner-approved 2026-11-17): the Khotab pattern through the shared protected proxy. The item page has no visibility filter; neither does this. */
    public function download(int $telawah, Request $request, ProxiedDownloadResponder $responder): Response
    {
        $telawahItem = TelawahItem::select(['id', 'link', 'linksize', 'title'])->findOrFail($telawah);
        // F04 fix: the normalised archive.org URL (ArchiveOrgLink, as the player); other links unchanged.
        $link = ArchiveOrgLink::normalize((string) $telawahItem->link);

        return $responder->respond($request, $link,
            new DownloadTelemetryContext('telawah', 'telawah_item', (int) $telawahItem->id, 'primary'),
            fn () => $telawahItem->incrementDownloadCount(),
            fn () => new DownloadRejectionContext(module: 'telawah', resourceType: 'telawah_item', resourceId: (int) $telawahItem->id,
                variant: 'primary', resolvedDownloadUrl: $link, storedSize: (int) $telawahItem->linksize, resourceTitle: $telawahItem->title),
            $telawahItem->getConnectionName());
    }
}
