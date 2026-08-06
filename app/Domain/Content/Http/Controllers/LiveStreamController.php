<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Replaces live-stream/live-stream.php, live-channel.php, live.php
 * (Roadmap task 3.2). See live-stream.md and the direct reads this port
 * is grounded in for full traceability; key points restated at each
 * action below rather than only in one place.
 */
class LiveStreamController
{
    /** live-stream/live-stream.php — channel directory + "most viewed channels" sidebar. */
    public function index(ContentSidebarWidget $sidebar): View
    {
        $channels = Channel::eligibleForLiveStream()
            ->orderByRaw($this->titleOrderClause())
            ->get(['id', 'title']);

        $mostViewedChannels = $sidebar->mostViewedLiveChannels();

        return view('live-stream.index', compact('channels', 'mostViewedChannels'));
    }

    /**
     * live-stream/live-channel.php — watch one channel.
     *
     * Eligibility check is `active = 0 AND id = ?` ONLY — deliberately NOT
     * the fuller eligibleForLiveStream() scope (IF-009): live-channel.php's
     * own query never checks streamcode emptiness, unlike the directory
     * listing. A channel with an empty streamcode is unreachable from the
     * directory page but still directly viewable by URL, matching legacy
     * exactly rather than "fixing" it to be consistent.
     *
     * ch_visits increment (via Channel::recordView(), §Wave 3's
     * TracksViews::viewCountColumn() generalization) always fires here —
     * contrast with featured() below, which never does.
     */
    public function show(int $channel, ContentSidebarWidget $sidebar): View
    {
        // findOrFail(), not find()+abort_if() (pre-Wave-4 decision #4) —
        // Laravel's default exception handler already converts
        // ModelNotFoundException to a 404 response; the longer form was
        // strictly more code for an identical observable result. Legacy
        // itself used die("Invalide Access") — HTTP 200, plain text, no
        // real status code — so 404 was already a deliberate,
        // technical-correctness change independent of this cleanup.
        $channelModel = Channel::where('active', 0)->findOrFail($channel);

        $channelModel->recordView();

        $mostViewed = $sidebar->liveStreamMostViewed($channel);
        $mostRecent = $sidebar->liveStreamMostRecent($channel);

        return view('live-stream.show', [
            'channel' => $channelModel,
            'mostViewed' => $mostViewed,
            'mostRecent' => $mostRecent,
        ]);
    }

    /**
     * live-stream/live.php — a hardcoded single-channel page (legacy
     * `$id = 51`, no `$_GET` read at all). Purpose unconfirmed
     * (live-stream.md §5/§8 — Business Confirmation candidate, not
     * resolved here). Preserved exactly as found:
     *  - hardcoded channel id 51, not configurable;
     *  - only live_channel_script() renders — no details, no sidebar;
     *  - IF-010: does NOT call recordView() — the only channel view in
     *    the whole module that never increments ch_visits (confirmed by
     *    the complete absence of an UPDATE statement in live.php).
     * No .htaccess rule exists for this page (confirmed) — reachable only
     * via its raw legacy path, same profile as Wave 2's static pages.
     */
    public function featured(): View
    {
        $channel = Channel::where('active', 0)->findOrFail(51);

        return view('live-stream.featured', compact('channel'));
    }

    /**
     * MySQL's `ORDER BY BINARY title ASC` (live-stream/functions.php:6)
     * forces byte-exact (case/accent-sensitive) comparison, unlike the
     * server's likely case-insensitive default collation. SQLite has no
     * BINARY keyword in this position (confirmed — it's a syntax error,
     * not merely a no-op), so this is driver-aware: production (MySQL)
     * gets the exact legacy clause; the sqlite branch exists only so the
     * test suite can execute this query at all. Not a behavior change in
     * production — the only environment where behavior is observable.
     */
    private function titleOrderClause(): string
    {
        return DB::connection('main')->getDriverName() === 'sqlite'
            ? 'title ASC'
            : 'BINARY title ASC';
    }
}
