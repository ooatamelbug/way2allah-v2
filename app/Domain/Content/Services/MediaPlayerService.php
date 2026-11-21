<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Models\FatwaQuestion;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\TelawahItem;
use App\Domain\Content\Support\BrowserPlaybackFormat;
use App\Domain\Content\Support\VarietyMediaLink;
use App\Support\Downloads\ExternalMediaLink;
use App\Support\Media\ArchiveOrgLink;

/**
 * Replaces `functions.php`'s `get_w2a_mada()` (:856-869) + `w2a_mada_play()`
 * (:794-855) — the shared, cross-module AJAX in-page player backing
 * `w2a_play()`/`get-mada-player.htm`. Confirmed shared infrastructure
 * (khotab-item-298784.htm Batch 4 investigation): also called by anasheed,
 * telawah, fatawa, and chat_room. `khotab`/`khotab_mirror` (Batch 4),
 * `anasheed`/`anasheed_mirror` (var-item-{id}.htm parity batch), `fatawa`
 * (`fatawa-all-{id}.htm` owner-approved `answer2.php` reconstruction),
 * and `telawat` are implemented through the same validated endpoint.
 *
 * **`fatawa` is NOT a straight port of `get_w2a_mada()`'s branch list**
 * (`functions.php:856-869` has no `'fatawa'` case at all — that type is
 * unhandled there). The real behavior lives one level up, in
 * `get_w2a_mada_player()` (`functions.php:870-896`): its own
 * `if($type=='fatawa')` branch (:881-888) bypasses `get_w2a_mada()`
 * entirely and plays whatever `$title`/`$link` the client already POSTed
 * (`answer2.php`'s inline `w2a_play()` reads them from the hidden
 * `<video><source>` tag it already rendered server-side from
 * `$question->media_link`) — and, because both arms of its
 * `$_mada->media_type=='video'` if/else call the exact same
 * `w2a_mada_play($title,$link,"video")`, the type is unconditionally
 * "video" regardless of the row's actual `media_type` column (a real,
 * confirmed no-op branch, not reproduced as a branch here). Resolving
 * server-side by `id` (`fromFatwaQuestion()` below) rather than trusting
 * a client-POSTed link is the same parameterized-lookup hardening already
 * applied to every other branch in this class (see this class's own
 * "Security, not a behavior change" note below) — not a new exception.
 *
 * **Security, not a behavior change:** `get_w2a_mada()` builds its SQL by
 * concatenating `$id` (raw `$_POST`/`$_GET` input) directly into the query
 * string — a confirmed SQL injection in legacy. This uses Eloquent
 * (`::find()`), parameterized by construction; the `int $id` type-hint on
 * every public method here rejects non-numeric input before it ever
 * reaches a query. Link values are also `e()`-escaped when embedded into
 * the returned HTML's attributes (legacy embeds `$link` completely raw)
 * — hardening against stored-XSS from a malicious `link` value, invisible
 * for the well-formed URLs real data actually has.
 *
 * `hidden = 0` filtering applied on both lookups — legacy's own
 * `get_w2a_mada()` has no such filter, but every caller of this service
 * only ever reaches a mirror/item whose parent page has already 404'd on
 * `hidden`, so this changes nothing reachable — same defensive
 * consistency already applied throughout `KhotabItemController`.
 */
class MediaPlayerService
{
    /**
     * `w2a_play(id, type)` → `get-mada-player.htm` → this. Returns the
     * player HTML fragment, or `null` for an unsupported `$type`, a
     * nonexistent/hidden `$id`, or a media format this service doesn't
     * render a player for (see `renderPlayer()`'s own docblock).
     */
    public function play(string $type, int $id): ?string
    {
        $media = $this->resolveMedia($type, $id);

        if ($media === null) {
            return null;
        }

        // B2 / F07-A (decision-log #147): a Variety link that is not an absolute http(s) URL with a host (bare or
        // concatenated file names, a character before the scheme, host-less paths, empty) would only start a player
        // whose request fails on this site. Say so instead, without any player. A link in an unsupported format keeps
        // the F05-2 explanation below (it already tells the visitor no download is available for such a link).
        if (in_array($type, ['anasheed', 'anasheed_mirror'], true)
            && ! VarietyMediaLink::isPlayable($media->link) && ! BrowserPlaybackFormat::isUnsupported($media->link)) {
            return '<div class="w2a-player-status w2a-player-invalid-link" role="status"><p>'.e(self::INVALID_LINK_MESSAGE).'</p></div>';
        }

        $player = $this->renderPlayer($media->title, $media->link, $media->video);

        // B2 / F05-2 Part 2 (Option B, decision-log #144): Khotab and Variety only (the branches that carry a
        // `download` resolver). A format no browser plays gets an explanation instead of the generic «غير متاحة»,
        // and no player. Every other unrenderable link (unknown formats included) keeps the existing empty response.
        if ($player === null && isset($media->download) && BrowserPlaybackFormat::isUnsupported($media->link)) {
            return $this->unsupportedFormatNotice(($media->download)());
        }

        return $player;
    }

    public const UNSUPPORTED_FORMAT_MESSAGE = 'هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح.';

    public const UNSUPPORTED_FORMAT_DOWNLOAD_HINT = 'يمكنك تحميل المادة وتشغيلها باستخدام برنامج يدعم هذه الصيغة.';

    public const UNSUPPORTED_FORMAT_NO_DOWNLOAD = 'لا يتوفر رابط تحميل لهذه المادة حالياً.';

    /** F07-A: owner-approved wording for a Variety link that cannot be played as stored. */
    public const INVALID_LINK_MESSAGE = 'رابط تشغيل هذه المادة غير صالح حاليًا. يرجى إبلاغ إدارة الموقع بالمشكلة.';

    /**
     * The owner-approved explanation (Option B). The download action is the page's own existing download route,
     * shown only when that route can serve the link; otherwise the second sentence promises nothing.
     */
    private function unsupportedFormatNotice(?string $downloadUrl): string
    {
        if ($downloadUrl === null) {
            return '<div class="w2a-player-status w2a-player-unsupported" role="status"><p>'
                .e(self::UNSUPPORTED_FORMAT_MESSAGE.' '.self::UNSUPPORTED_FORMAT_NO_DOWNLOAD).'</p></div>';
        }

        return '<div class="w2a-player-status w2a-player-unsupported" role="status"><p>'
            .e(self::UNSUPPORTED_FORMAT_MESSAGE.' '.self::UNSUPPORTED_FORMAT_DOWNLOAD_HINT).'</p>'
            .'<a href="'.e($downloadUrl).'" download class="w2a-action-btn w2a-action-download">'
            .'<i class="fa fa-download" aria-hidden="true"></i> <span>تحميل المادة</span></a></div>';
    }

    /**
     * Whether `/khotab-download-…` / `/khotab-mirror-…` can serve a stored link — the exact, network-free 404 rule of
     * `ProxiedDownloadStreamer::stream()` (empty link, or neither `http(s)://` nor an existing file), on the link that
     * route uses: the stored link after ArchiveOrgLink normalisation (F04 fix, 2026-11-17). The mirror route also answers YouTube / SoundCloud page links by redirect.
     * A remote source that fails later cannot be known without contacting it (never done here).
     */
    private static function khotabDownloadable(?string $link, bool $mirror): bool
    {
        if ($mirror && $link !== null && ExternalMediaLink::classify($link) !== null) {
            return true;
        }

        // F04 fix (2026-11-17): the route now fetches the normalised archive.org URL, so judge that same link.
        $link = $link === null ? null : ArchiveOrgLink::normalize($link);

        return $link !== null && $link !== ''
            && (str_starts_with($link, 'http://') || str_starts_with($link, 'https://') || is_file($link));
    }

    /**
     * Whether `/var-download-…` / `/var-mirror-…` can serve the link: F07-A moved this rule to
     * {@see VarietyMediaLink::isDownloadable()}, which now mirrors the route exactly (Laravel's own URL validation, so a
     * leading space on a non-archive.org link — redirected into this site — is no longer offered).
     */
    private static function anasheedDownloadable(?string $link): bool
    {
        return VarietyMediaLink::isDownloadable($link);
    }

    /**
     * `get_w2a_mada($id, $type)`'s `khotab`/`khotab_mirror` (`functions.php:861-864`)
     * and `anasheed`/`anasheed_mirror` (`:865-868`) branches, plus `fatawa`
     * (this class's own `get_w2a_mada_player()`-level reconstruction, see
     * class docblock) and the final UI branch's `telawat` player.
     *
     * @return object{title: string, link: string, video: bool}|null
     */
    private function resolveMedia(string $type, int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        if ($type === 'khotab') {
            return $this->fromKhotabItem($id);
        }

        if ($type === 'khotab_mirror') {
            return $this->fromMirror($id);
        }

        // var-item-{id}.htm parity: functions.php:856-869's get_w2a_mada()
        // also handles 'anasheed'/'anasheed_mirror' — confirmed both are
        // genuinely reachable (real w2a_play(id,'anasheed')/w2a_play(id,
        // 'anasheed_mirror') calls in anasheed_details()/list_anasheed_mirrors()),
        // not deferred like 'telawat'. Same shape as khotab's two
        // branches above — purely additive, khotab/khotab_mirror unchanged.
        if ($type === 'anasheed') {
            return $this->fromAnasheedItem($id);
        }

        if ($type === 'anasheed_mirror') {
            return $this->fromAnasheedMirror($id);
        }

        if ($type === 'fatawa') {
            return $this->fromFatwaQuestion($id);
        }

        if ($type === 'telawat') {
            return $this->fromTelawahItem($id);
        }

        return null;
    }

    private function fromKhotabItem(int $id): ?object
    {
        $item = KhotabItem::where('hidden', 0)->find($id);

        if ($item === null) {
            return null;
        }

        // F05-1 (owner-approved, 2026-11-14): the same render-time archive.org normalisation as the Anasheed
        // branches (retired storage nodes and http → https://archive.org/download/…). The stored link is not
        // changed; other hosts and the file name/extension pass through untouched.
        return (object) [
            'title' => (string) $item->title,
            'link' => ArchiveOrgLink::normalize((string) $item->link),
            'video' => (bool) $item->vedio,
            'download' => fn (): ?string => self::khotabDownloadable($item->link, false) ? '/khotab-download-'.$item->id.'.htm' : null,
        ];
    }

    /** `get_w2a_mada()`'s own aliased `comment AS title` for mirrors; archive.org links normalised as above (F05-1). */
    private function fromMirror(int $id): ?object
    {
        $mirror = Mirror::where('hidden', 0)->find($id);

        if ($mirror === null) {
            return null;
        }

        // B2 / F05-2: the parent's flag is read only for the one case it can change (an audio-flagged `.mp4`), and
        // without a `hidden` filter — visibility is decided exactly as before, by the mirror lookup above.
        $video = (bool) $mirror->vedio;
        if (! $video && BrowserPlaybackFormat::pathExtension((string) $mirror->link) === 'mp4') {
            $video = self::khotabMirrorPlaysAsVideo($mirror->vedio, KhotabItem::whereKey($mirror->khid)->value('vedio'), (string) $mirror->link);
        }

        return (object) [
            'title' => (string) $mirror->comment,
            'link' => ArchiveOrgLink::normalize((string) $mirror->link),
            'video' => $video,
            'download' => fn (): ?string => self::khotabDownloadable($mirror->link, true) ? '/khotab-mirror-'.$mirror->khid.'-'.$mirror->id.'.htm' : null,
        ];
    }

    /**
     * B2 / F05-2 (owner-approved 2026-11-16, decision-log #143) — whether a Khotab mirror plays (and is presented) as
     * video. The single rule shared by `fromMirror()` and the quality card (`media-quality-list`):
     *
     * - the mirror's own `vedio` flag, when it says video (unchanged);
     * - otherwise **video only when BOTH** the parent item is a video item (`vedio = 1`) **and** the mirror's URL
     *   path ends in `.mp4` (case-insensitive). 601 such `.mp4` mirrors of video items are flagged audio in the data,
     *   so they got no `<video>` and showed «غير متاحة للتشغيل».
     *
     * The parent type alone never makes a mirror video (an `.rmvb`, `.wmv` or `.mp3` mirror keeps its flag), and `mp4`
     * elsewhere in the URL (query string, host, a file named `…mp4.wmv`) does not count. Render-time only: no stored
     * flag changes. **Intentionally differs from Gen 2**, whose `w2a_mada_play()` routes these mirrors to its audio
     * branch and fails the same way.
     */
    public static function khotabMirrorPlaysAsVideo(mixed $mirrorVedio, mixed $itemVedio, string $link): bool
    {
        if ((bool) $mirrorVedio) {
            return true;
        }

        return (int) $itemVedio === 1 && BrowserPlaybackFormat::pathExtension($link) === 'mp4';
    }

    private function fromAnasheedItem(int $id): ?object
    {
        $item = AnasheedItem::where('hidden', 0)->find($id);

        if ($item === null) {
            return null;
        }

        // OLD Play(): `fix_archive_links()` before ShowPlayer (visitor parity A2, 2026-11-04).
        return (object) [
            'title' => (string) $item->title,
            'link' => ArchiveOrgLink::normalize((string) $item->link),
            'video' => (bool) $item->vedio,
            'download' => fn (): ?string => self::anasheedDownloadable($item->link) ? '/var-download-'.$item->id.'.htm' : null,
        ];
    }

    /**
     * `get_w2a_mada()`'s own `title AS title` for anasheed mirrors (khotab_mirror uses `comment AS title` instead — a real, distinct column).
     *
     * Visitor parity (2026-11-04): OLD PlayMirror() plays a mirror only while its item is `hidden='0'`
     * (A6), and normalises archive.org links (A2).
     */
    private function fromAnasheedMirror(int $id): ?object
    {
        $mirror = AnasheedMirror::where('hidden', 0)->find($id);

        if ($mirror === null || ! AnasheedItem::whereKey($mirror->khid)->where('hidden', 0)->exists()) {
            return null;
        }

        return (object) [
            'title' => (string) $mirror->title,
            'link' => ArchiveOrgLink::normalize((string) $mirror->link),
            'video' => (bool) $mirror->vedio,
            // The player already required the item to be visible, as `/var-mirror-…` does.
            'download' => fn (): ?string => self::anasheedDownloadable($mirror->link) ? '/var-mirror-'.$mirror->khid.'-'.$mirror->id.'.htm' : null,
        ];
    }

    private function fromTelawahItem(int $id): ?object
    {
        // The telawah detail route intentionally exposes rows regardless
        // of the legacy hidden flag, so playback follows the same rule.
        $item = TelawahItem::find($id);

        if ($item === null) {
            return null;
        }

        return (object) [
            'title' => (string) $item->title,
            'link' => (string) $item->link,
            // Gen 2 get_w2a_mada_player(): audio branch only when `vedio` is 0, else video (e.g. vedio=1 .mp4).
            'video' => (bool) $item->vedio,
        ];
    }

    /**
     * `get_w2a_mada_player()`'s `type=='fatawa'` branch — see this class's
     * docblock for why `video` is unconditionally `true` (the row's own
     * `media_type` column is never actually consulted in legacy, both
     * arms of that dead if/else call the same thing) rather than reading
     * `FatwaQuestion::media_type`. No `hidden` column exists on this table
     * (confirmed absent from `FatwaQuestion`'s own column list) — no
     * filter to apply here, unlike the other `from*()` methods.
     */
    private function fromFatwaQuestion(int $id): ?object
    {
        $question = FatwaQuestion::find($id);

        if ($question === null) {
            return null;
        }

        return (object) [
            'title' => (string) $question->question_text,
            'link' => (string) $question->media_link,
            'video' => true,
        ];
    }

    /**
     * `w2a_mada_play($title, $link, $type)` (`functions.php:794-855`) —
     * only the branches that still work in any current browser:
     * YouTube (video only), native `<audio>` for `.mp3` (either video or
     * audio — legacy checks this in BOTH its `audio` and `video`
     * branches, reproduced here as one unconditional check), native
     * `<video>` for `.mp4` (video only), and SoundCloud (audio only,
     * matching legacy's own audio-branch-only check).
     *
     * **Deliberately NOT implemented — pending a product decision, not
     * silently decided here:** legacy's RealPlayer ActiveX (`rm`/`rmvb`/
     * `ra`/`ram`/other non-mp3 audio), Windows Media Player ActiveX
     * (`avi`/`wmv`/`asf`/`divx`/`div`), and Flash `.swf` (the catch-all
     * fallback) branches — all non-functional in every current browser,
     * confirmed live on production too (not just hypothetically). Real
     * khotab data has ~14,835 items (~5.1% of 292,482 linked items) on
     * these formats — material, not negligible; see the implementation
     * report for the full breakdown. Returns `null` for these (and any
     * other unrecognized format) rather than embedding dead technology or
     * inventing a fallback UI. (B2 / F05-2 Part 2, decision-log #144: for the
     * Khotab and Variety types, `play()` then answers the approved list of
     * these formats with an explanation and the existing download route —
     * still never a player.)
     */
    private function renderPlayer(string $title, string $link, bool $video): ?string
    {
        if ($video && str_contains($link, 'youtu')) {
            $youtubeId = $this->youtubeId($link);

            if ($youtubeId === null) {
                return null;
            }

            return '<div class="w2a-video-player-wrapper"><div class="embed-responsive embed-responsive-16by9"><iframe src="https://www.youtube.com/embed/'.e($youtubeId).'?autoplay=1" title="'.e($title).'" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>';
        }

        $extension = strtolower(pathinfo(parse_url($link, PHP_URL_PATH) ?: $link, PATHINFO_EXTENSION));

        if ($extension === 'mp3') {
            return $this->audioPlayer($title, '<audio controls autoplay><source src="'.e($link).'" type="audio/mpeg"></audio>');
        }

        if (! $video && str_contains($link, 'soundcloud.com')) {
            $embedUrl = 'https://w.soundcloud.com/player/?url='.$link.'&color=%2310b981&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true';

            return $this->audioPlayer($title, '<iframe width="100%" height="166" loading="lazy" title="'.e($title).'" allow="autoplay" src="'.e($embedUrl).'"></iframe>');
        }

        if ($video && $extension === 'mp4') {
            return '<div class="w2a-video-player-wrapper"><video controls autoplay playsinline preload="metadata"><source src="'.e($link).'" type="video/mp4"></video></div>';
        }

        return null;
    }

    private function audioPlayer(string $title, string $player): string
    {
        $bars = str_repeat('<span class="w2a-audio-anim-bar"></span>', 5);

        return '<div class="w2a-audio-player-wrapper"><div class="w2a-audio-anim-bars" aria-hidden="true">'.$bars.'</div><h4>'.e($title).'</h4>'.$player.'</div>';
    }

    /** Resolve watch, Shorts, embed, live, and short URLs without query fragments. */
    private function youtubeId(string $link): ?string
    {
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        $path = trim((string) parse_url($link, PHP_URL_PATH), '/');
        $id = null;

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = explode('/', $path)[0];
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
            if ($path === 'watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^(?:shorts|embed|live)/([^/]+)$~', $path, $matches)) {
                $id = $matches[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
}
