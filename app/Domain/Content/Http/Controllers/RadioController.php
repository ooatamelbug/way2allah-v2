<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Replaces `radio/index.php` — Roadmap task 4.10 (added post-Wave-4, see
 * docs/reviews/legacy-vs-laravel-coverage.md §4 and
 * docs/reviews/gap-closure-action-plan.md item 2).
 *
 * Only `radio/index.php`'s page is ported — the anonymous, non-personalized
 * continuous-playlist view. `radio/index.php` is confirmed (by direct
 * reading) to be the file all 5 live `.htaccess` rules actually route to,
 * and it contains no `op=` handling whatsoever: it unconditionally renders
 * this one page regardless of query parameters. The personalized-playlist
 * backend (`delete_playlist_item()`/`get_playlist_item()`/
 * `save_last_listen()`, `radio/functions.php`) is real, working code — but
 * it is only ever called from `radio/indexXX.php`, a second entry point
 * that no `.htaccess` rule points to. It is unreachable, confirmed dead
 * code, not merely undocumented — see IF-032. NOT ported.
 *
 * `mobile_me` (`radio/index.php:28`, `detect_if_mobile_view()`) toggles a
 * hidden form field the frontend JS reads — reproduced as a query-string
 * check only (`?mobile_me=true`, matching the one real `.htaccess` rule
 * that sets it: `radio-mobile.htm`). The legacy function's ~300-char
 * user-agent regex fallback is not reproduced: it's a pre-viewport-meta-tag
 * era sniffing pattern with no confirmed current purpose beyond what the
 * query-string flag already covers for this app's own two real entry
 * points, and inventing a modern equivalent is out of scope for a
 * behavior-preservation pass — flagged, not silently dropped.
 */
class RadioController
{
    public function index(ContentSidebarWidget $sidebar): View
    {
        $playlist = $this->presentPlaylist($sidebar->radioPlaylist());
        $newestVideo = $sidebar->radioMostRecentByVideoFlag(true);
        $newestAudio = $sidebar->radioMostRecentByVideoFlag(false);

        return view('radio.index', compact('playlist', 'newestVideo', 'newestAudio'));
    }

    /**
     * `radio/index.php:107-120`'s per-row presentation logic — which link
     * is actually playable, and this playlist item's section label.
     * Reproduced exactly: video rows always use the mirror link; audio
     * rows use the main link only if it's actually an `.mp3` URL,
     * otherwise fall back to the mirror link.
     */
    private function presentPlaylist(Collection $rows): Collection
    {
        return $rows->map(function (object $row) {
            if ((int) $row->media_type === 1) {
                $row->audio_url = $row->mirror_link;
                $row->pl_section = 'video';
            } else {
                $row->audio_url = str_contains(strtolower((string) $row->main_link), '.mp3')
                    ? $row->main_link
                    : $row->mirror_link;
                $row->pl_section = 'audio';
            }

            return $row;
        });
    }
}
