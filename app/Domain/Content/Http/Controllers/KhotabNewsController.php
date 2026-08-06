<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/news.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * IF-017's fix: the `pdf` op's "Most Downloaded" sidebar box is scoped to
 * `pdf > 0` content (`ContentSidebarWidget::khotabMostDownloadedForPdf()`),
 * not an unset `$ob->video` coerced to `vedio=0`. The main list itself was
 * never buggy — `ListKhotab(mode='pdf')` doesn't touch `$ob->video` at all.
 */
class KhotabNewsController
{
    public function show(string $op, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $op = in_array($op, ['video', 'pdf'], true) ? $op : 'audio';

        $fixedItems = collect();

        if ($op === 'pdf') {
            $items = $listing->khotabItemsWithPdf();
            $mostDownloaded = $sidebar->khotabMostDownloadedForPdf();
        } else {
            $video = $op === 'video';
            $items = $listing->khotabItemsFixedOrNew(0, 0, 0, $video, false);
            $fixedItems = $listing->khotabItemsFixedOrNew(0, 0, 0, $video, true);
            $mostDownloaded = $sidebar->khotabMostDownloadedByVideoFlag($video);
        }

        $randomFeatured = $sidebar->khotabRandomFeatured();

        return view('khotab.news', compact('op', 'items', 'fixedItems', 'mostDownloaded', 'randomFeatured'));
    }
}
