<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/fatawa-channels.php` (channel directory) and
 * `fatawa/channel_fatawa.php` (one channel's fatwa list) — Roadmap task
 * 6.1, increment 2. Legacy files, routes, and `op=`/parameter shapes
 * re-verified directly against `.htaccess:288-292` before implementing
 * (not inferred from `fatawa.md`'s summary table) — see `fatawa.md`'s
 * increment-2 addendum for the full literal mapping.
 */
class FatwaChannelController
{
    /**
     * `fatawa-channels.php` — directory of channels with at least one
     * fatwa question. `$perpage=30` here (a confirmed local override,
     * `fatawa-channels.php:5` — not the sitewide 25). Legacy also always
     * shows a "no channel" (`id=0`) entry first, ahead of the paginated
     * list (`fatawa-channels.php:38-44`) — reproduced identically.
     */
    public function index(int $page, ContentListingService $listing): View
    {
        $channels = $listing->fatwaChannelsWithQuestions($page);

        return view('fatawa.channels-index', compact('channels'));
    }

    /**
     * `channel_fatawa.php` — one channel's fatwa questions
     * (`ContentListingService::fatwaQuestionsForChannel()`), plus a
     * channel-scoped "most downloaded"/"newest" sidebar
     * (confirmed genuinely channel-filtered, unlike the author page's
     * equivalent — see `ContentSidebarWidget::fatwaMostDownloadedByChannel()`'s
     * docblock).
     */
    public function show(int $channel, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $channelModel = Channel::findOrFail($channel);

        $generalQuestions = $listing->fatwaQuestionsForChannel($channel, $page);
        $mostDownloaded = $sidebar->fatwaMostDownloadedByChannel($channel);
        $mostRecent = $sidebar->fatwaMostRecentByChannel($channel);

        return view('fatawa.channel-show', compact('channelModel', 'generalQuestions', 'mostDownloaded', 'mostRecent'));
    }
}
