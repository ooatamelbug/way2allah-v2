<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Replaces channels/channels.php, channel.php, author.php (Roadmap task
 * 3.2). channels/item.php, authors.php, channe___l.php, old.php are
 * confirmed orphaned (channels.md §2 — no .htaccess rule, not included by
 * any other file) and are not ported, per ADR-0010's "dead code is never
 * automatically a migration requirement."
 *
 * show() and showAuthor() are deliberately separate actions, not one
 * method branching on whether an author id is present — channel.php and
 * author.php differ in one easy-to-lose way (see showAuthor()'s
 * docblock), and collapsing them into a conditional risked silently
 * "fixing" that difference by accident.
 */
class ChannelController
{
    /** channels/channels.php — full directory, no eligibility filter at all (unlike live-stream's), ordered by `khotab` desc. */
    public function index(): View
    {
        $channels = Channel::orderByDesc('khotab')->get();

        // channels.php:28 sets the panel's title from an undefined
        // $Anasheed variable ($Anasheed->title) — a confirmed copy-paste
        // artifact (IF-011), not a real feature. Current production
        // behavior is a blank panel title (PHP warning, null result), not
        // "Channels" or any other sensible text — reproduced as blank
        // rather than inventing a title that was never actually there.
        return view('channels.index', ['channels' => $channels, 'panelTitle' => '']);
    }

    /**
     * channels/channel.php — browse one channel's groups/series/items,
     * with a working "most downloaded"/"newest" sidebar (via topitems(),
     * see ContentSidebarWidget's Wave 3 additions).
     */
    public function show(int $channel, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        // findOrFail() (pre-Wave-4 decision #4) — see LiveStreamController::show()'s
        // comment for why. Legacy has no empty-check on $Channel at all
        // here (unlike live-stream's explicit die()) — it reads
        // $Channel->title unconditionally, which would warn rather than
        // fail cleanly on a genuinely missing channel. 404 is the same
        // technical-correctness call already made consistently elsewhere.
        $channelModel = Channel::findOrFail($channel);

        $groups = $listing->groupsByChannel($channel, 0, true);
        $series = $listing->seriesByChannel($channel, 0, true);
        $items = $listing->khotabItemsByChannel($channel, 0, 0, 0, true);
        $mostDownloaded = $sidebar->channelMostDownloadedKhotabItems($channel);
        $mostRecent = $sidebar->channelMostRecentKhotabItems($channel);

        return view('channels.show', compact('channelModel', 'groups', 'series', 'items', 'mostDownloaded', 'mostRecent'));
    }

    /**
     * channels/author.php — browse one channel filtered to one author.
     *
     * IF-012: this page's "الأكثر تحميلا" (Most Downloaded) / "جديد
     * المواد" (Newest) sidebar boxes are confirmed EMPTY in legacy —
     * `w2a_open_div($data); w2a_close_div();` with nothing rendered
     * between them, unlike channel.php's otherwise-similar page, which
     * does populate the same-titled boxes via topitems(). Preserved
     * exactly: this action does NOT call channelMostDownloadedKhotabItems()/
     * channelMostRecentKhotabItems() at all — populating them would be
     * inventing behavior author.php has never actually had.
     *
     * The "اخترنا لك هذه المادة" (Recommended For You) box
     * (author.php:81, randomitems()) is also not reproduced — it needs a
     * real content-item model to pick a random row from, which doesn't
     * exist until Wave 4. Documented as deferred scope, not a silent gap
     * (Wave 3 report, Technical Debt).
     */
    public function showAuthor(int $channel, int $author, ContentListingService $listing): View
    {
        $channelModel = Channel::findOrFail($channel);

        $authorRow = DB::connection('main')->table('nuke_islamic_authors')->find($author);

        $groups = $listing->groupsByChannel($channel, $author, true);
        $series = $listing->seriesByChannel($channel, $author, true);
        $items = $listing->khotabItemsByChannel($channel, $author, 0, 0, true);

        return view('channels.author', compact('channelModel', 'authorRow', 'author', 'groups', 'series', 'items'));
    }
}
