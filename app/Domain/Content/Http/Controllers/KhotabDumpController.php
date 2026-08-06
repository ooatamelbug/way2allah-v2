<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/** Replaces `khotab/dump.php` — "latest 50 transcribed lessons." Roadmap task 4.1. */
class KhotabDumpController
{
    public function index(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $items = $listing->khotabPdfDump();
        $mostDownloaded = $sidebar->khotabMostDownloadedForPdf();

        return view('khotab.dump', compact('items', 'mostDownloaded'));
    }
}
