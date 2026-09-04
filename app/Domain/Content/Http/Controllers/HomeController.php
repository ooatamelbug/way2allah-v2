<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Admin\Models\SiteOption;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * G-02 (Migration Gap Register) — replaces `index.php` + `new_content.php`
 * + `home_functions.php`'s homepage-rendering functions. Full evidence
 * trail: "Homepage Migration — Implementation Blueprint" (chat deliverable,
 * this same task). `index.php`'s own dead `$_GET['op']`/`$title_ex`/
 * `$tb_field`/`$slug` block is deliberately NOT reproduced (confirmed dead
 * — never referenced again in that file).
 *
 * Kept a thin orchestrator: data assembly and the poll's preserved
 * comment-count quirk live here; presentation remains in `home.blade.php`.
 * The enhanced v1 homepage intentionally removed the gallery carousel and
 * advertising card, so their queries and ad view-count side effects are not
 * executed for a surface that no longer renders them.
 */
class HomeController
{
    public function index(ContentListingService $listing): View
    {
        return view('home', [
            'videos' => $this->withVideoTimeLabel($this->withVideoThumbs($listing->homeLatestVideos())),
            'cat487' => $listing->homeCategory487(),
            'fatawas' => $this->withFatwaLinkIds($listing->homeLatestFatawas()),
            'exclusive158' => $this->withAnasheedThumbs($listing->homeAnasheedByParent(158), 72, 50),
            'youtube' => $this->resolveYoutube(),
            'soundcloud' => $this->resolveSoundcloud(),
            'telawahs' => $listing->homeLatestTelawahs(),
            'audios' => $listing->homeLatestAudios(),
            'documentary12' => $this->withAnasheedThumbs($listing->homeAnasheedByParent(12), 72, 50),
            'cartoon57' => $this->withAnasheedThumbs($listing->homeAnasheedByParent(57), 72, 50),
            'dumpFiles' => $this->withVideoThumbs($listing->homeLatestDumpFiles()),
            'pollData' => $this->resolvePollViewData(),
            'trending' => $this->withAnasheedThumbs($listing->homeTrendingAnasheed(), 100, 75),
            'slides' => $listing->homeSliderItems(),
        ]);
    }

    /**
     * `home_functions.php:4-69`/`347-400` share this exact thumbnail
     * resolution shape: frame -> bucketed `khotab_frames` path,
     * `file_exists()`-gated; else author's OWN bucketed photo,
     * `file_exists()`-gated; else `tvnoise.gif`.
     *
     * `list_latest_videos()`'s per-item `nuke_islamic_series` lookup
     * (building `$SeriesTitle`) is deliberately NOT reproduced — direct
     * re-read confirms its result is computed but never echoed anywhere
     * in that function's `<li>` output (dead code, zero observable
     * effect). Reproducing it would only add an unused N+1 query per
     * item with no output difference — out of scope per "performance
     * optimization not explicitly required" / this is removal of a
     * no-op, not a behavior change.
     */
    private function withVideoThumbs(Collection $items): Collection
    {
        return $items->map(function ($item) {
            $item->thumb = $this->bucketedThumb((int) $item->id, (int) $item->frame, isset($item->thid) ? (int) $item->thid : null);

            return $item;
        });
    }

    /**
     * `home_functions.php:47-49`'s time label, `list_latest_videos()`
     * only (`get_latest_dump_files()` has no equivalent span in its
     * output — confirmed by direct re-read, not reproduced there).
     * Preserved exactly, including the `(hour - 1)` quirk: `$H =
     * ((date("h",$time)-1)==0) ? "12" : (date("h",$time)-1);` — not
     * "fixed" to a normal 12-hour display.
     */
    private function withVideoTimeLabel(Collection $items): Collection
    {
        return $items->map(function ($item) {
            if (! property_exists($item, 'time') || $item->time === null) {
                return $item;
            }

            $hour = ((int) date('h', (int) $item->time)) - 1;
            $item->timeLabel = ($hour === 0 ? '12' : (string) $hour).':'.date('i', (int) $item->time).(date('a', (int) $item->time) === 'am' ? 'ص' : 'م');

            return $item;
        });
    }

    private function bucketedThumb(int $id, int $frame, ?int $authorId): string
    {
        if ($frame === 1) {
            $rel = MediaPathResolver::path('khotab_frames', $id, 'jpg');
            if (file_exists(public_path($rel))) {
                return MediaUrl::asset($rel);
            }

            return '/images/tvnoise.gif';
        }

        if ($authorId !== null) {
            $rel = MediaPathResolver::path('authors', $authorId, 'jpg');
            if (file_exists(public_path($rel))) {
                return MediaUrl::asset($rel);
            }
        }

        return '/images/tvnoise.gif';
    }

    /**
     * `functions.php:150-187`'s `thumbnail()` — the DIFFERENT thumbnail
     * convention used by `listvars()` (sections 6/13/14) and the inline
     * trending query (section 17): unlike `withVideoThumbs()` above, this
     * one does NOT check `file_exists()` — it trusts the `frame` flag
     * unconditionally and routes through `thumbnails.php`'s resize
     * endpoint (confirmed by direct re-read of `thumbnail()`, a
     * correction to this task's own Blueprint, which stated only the
     * album section touches `thumbnails.php` — see the Final Report's
     * "Deviations" section for the full explanation). `thumbnails.php`
     * remains legacy-served under this migration's side-by-side
     * coexistence architecture (ADR-0001), so the identical relative URL
     * legacy builds is reproduced as-is — no new resize code.
     */
    private function anasheedThumb(int $id, int $frame, int $w, int $h): string
    {
        if ($frame === 1) {
            $rel = MediaPathResolver::path('anasheed/frame', $id, 'jpg');

            return MediaUrl::thumbnail("h={$h}&w={$w}&src=".$rel);
        }

        return '/images/tvnoise.gif';
    }

    private function withAnasheedThumbs(Collection $items, int $w, int $h): Collection
    {
        return $items->map(function ($item) use ($w, $h) {
            $item->thumb = $this->anasheedThumb((int) $item->id, (int) $item->frame, $w, $h);

            return $item;
        });
    }

    /**
     * `home_functions.php:70-93`'s `list_latest_fatawas()` pipe-prefix
     * parsing: `if ($the_id[0] == '|') { $the_id = explode('|', $the_id); $the_id = $the_id[1]; }`
     * — preserved exactly, including checking the first BYTE of the raw
     * string (not `str_starts_with`, which would be behaviorally
     * identical here but this makes the byte-index check explicit and
     * traceable back to the exact legacy line).
     */
    private function withFatwaLinkIds(Collection $items): Collection
    {
        return $items->map(function ($item) {
            $rawId = (string) $item->general_question_id;
            if (($rawId[0] ?? '') === '|') {
                $parts = explode('|', $rawId);
                $rawId = $parts[1] ?? $rawId;
            }
            $item->linkId = $rawId;

            return $item;
        });
    }

    /** `home_functions.php:208-223`'s `w2a_youtube()`. */
    private function resolveYoutube(): array
    {
        $raw = SiteOption::get('youtube');
        $decoded = is_string($raw) ? @unserialize($raw) : false;
        $ids = is_array($decoded) ? array_values($decoded) : [];

        if (count($ids) === 0) {
            return ['empty' => true];
        }

        return ['empty' => false, 'id' => $ids[array_rand($ids)]];
    }

    /** `home_functions.php:224-237`'s `w2a_soundcloud()`. */
    private function resolveSoundcloud(): array
    {
        $id = (int) SiteOption::get('soundcloud', 0);

        return $id > 0 ? ['empty' => false, 'id' => $id] : ['empty' => true];
    }

    /**
     * `home_functions.php:401-434`'s `print_polls()`.
     *
     * Comment-count quirk preserved: legacy casts a WHOLE ROW OBJECT
     * (`get_row(...)`, not a count query) to int via `intval($numcom)`.
     * PHP's object-to-int conversion yields exactly 1 for any non-null
     * object and 0 for `null`/no match — so the legacy-observable value
     * is always either 0 (no comment rows) or 1 (one or more comment
     * rows), never a real count. Reproduced by computing that same
     * resulting 0/1 value directly (an `exists()` check) rather than
     * performing an actual object-to-int cast in new code, which is
     * unnecessary and (unlike legacy PHP) not idiomatic here — the
     * OBSERVABLE bug (never a real count) is what's preserved, per
     * this task's explicit instruction not to replace it with
     * `COUNT(*)`.
     *
     * No standalone-poll-exists guard exists in legacy's own code (it
     * would render a form for a non-existent pollID=0 with an undefined
     * title). Real data has 146 standalone polls, so this edge case has
     * no production evidence either way — handled defensively here by
     * omitting the section entirely when null, flagged as a judgment
     * call in the Final Report, not a silent guess.
     */
    private function resolvePollViewData(): ?array
    {
        $poll = Poll::standalone()->orderByDesc('pollID')->first();

        if ($poll === null) {
            return null;
        }

        $options = PollOption::where('pollID', $poll->pollID)
            ->whereBetween('voteID', [1, 12])
            ->orderBy('voteID')
            ->get()
            ->filter(fn ($option) => trim((string) $option->optionText) !== '')
            ->values();

        $hasComments = DB::connection('main')->table('nuke_pollcomments')->where('pollID', $poll->pollID)->exists();

        return [
            'poll' => $poll,
            'options' => $options,
            'totalVotes' => $poll->totalVotes(),
            'commentsDisplay' => $hasComments ? 1 : 0,
        ];
    }
}
