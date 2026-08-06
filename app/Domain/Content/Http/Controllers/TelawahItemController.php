<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\TelawahItem;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function download(int $telawah): RedirectResponse
    {
        $telawahItem = TelawahItem::select(['id', 'link'])->findOrFail($telawah);

        $telawahItem->incrementDownloadCount();

        return redirect($telawahItem->link);
    }
}
