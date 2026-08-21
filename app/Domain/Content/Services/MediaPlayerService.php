<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;

/**
 * Replaces `functions.php`'s `get_w2a_mada()` (:856-869) + `w2a_mada_play()`
 * (:794-855) — the shared, cross-module AJAX in-page player backing
 * `w2a_play()`/`get-mada-player.htm`. Confirmed shared infrastructure
 * (khotab-item-298784.htm Batch 4 investigation): also called by anasheed,
 * telawah, fatawa, and chat_room. `khotab`/`khotab_mirror` (Batch 4) and
 * `anasheed`/`anasheed_mirror` (var-item-{id}.htm parity batch) are
 * implemented; `telawat`/`fatawa` are deliberately NOT added yet (out of
 * approved scope so far), matching the same `resolveMedia()` branch shape
 * they'd extend later without restructuring this class.
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

        return $this->renderPlayer($media->link, $media->video);
    }

    /**
     * `get_w2a_mada($id, $type)`'s `khotab`/`khotab_mirror` (`functions.php:861-864`)
     * and `anasheed`/`anasheed_mirror` (`:865-868`) branches — `telawat`/`fatawa`
     * remain deliberately unimplemented.
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
        // not deferred like 'telawat'/'fatawa'. Same shape as khotab's two
        // branches above — purely additive, khotab/khotab_mirror unchanged.
        if ($type === 'anasheed') {
            return $this->fromAnasheedItem($id);
        }

        if ($type === 'anasheed_mirror') {
            return $this->fromAnasheedMirror($id);
        }

        return null;
    }

    private function fromKhotabItem(int $id): ?object
    {
        $item = KhotabItem::where('hidden', 0)->find($id);

        if ($item === null) {
            return null;
        }

        return (object) [
            'title' => (string) $item->title,
            'link' => (string) $item->link,
            'video' => (bool) $item->vedio,
        ];
    }

    /** `get_w2a_mada()`'s own aliased `comment AS title` for mirrors. */
    private function fromMirror(int $id): ?object
    {
        $mirror = Mirror::where('hidden', 0)->find($id);

        if ($mirror === null) {
            return null;
        }

        return (object) [
            'title' => (string) $mirror->comment,
            'link' => (string) $mirror->link,
            'video' => (bool) $mirror->vedio,
        ];
    }

    private function fromAnasheedItem(int $id): ?object
    {
        $item = AnasheedItem::where('hidden', 0)->find($id);

        if ($item === null) {
            return null;
        }

        return (object) [
            'title' => (string) $item->title,
            'link' => (string) $item->link,
            'video' => (bool) $item->vedio,
        ];
    }

    /** `get_w2a_mada()`'s own `title AS title` for anasheed mirrors (khotab_mirror uses `comment AS title` instead — a real, distinct column). */
    private function fromAnasheedMirror(int $id): ?object
    {
        $mirror = AnasheedMirror::where('hidden', 0)->find($id);

        if ($mirror === null) {
            return null;
        }

        return (object) [
            'title' => (string) $mirror->title,
            'link' => (string) $mirror->link,
            'video' => (bool) $mirror->vedio,
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
     * inventing a fallback UI.
     */
    private function renderPlayer(string $link, bool $video): ?string
    {
        if ($video && str_contains($link, 'youtu')) {
            $youtubeId = $this->youtubeId($link);

            return '<div class="embed-responsive embed-responsive-16by9"><iframe src="https://www.youtube.com/embed/'.e($youtubeId).'" frameborder="0" allowfullscreen></iframe></div>';
        }

        $extension = strtolower(pathinfo(parse_url($link, PHP_URL_PATH) ?: $link, PATHINFO_EXTENSION));

        if ($extension === 'mp3') {
            return '<audio controls autoplay><source src="'.e($link).'" type="audio/mpeg"></audio>';
        }

        if (! $video && str_contains($link, 'soundcloud.com')) {
            $embedUrl = 'https://w.soundcloud.com/player/?url='.$link.'&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true';

            return '<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="'.e($embedUrl).'"></iframe>';
        }

        if ($video && $extension === 'mp4') {
            return '<video controls autoplay><source src="'.e($link).'" type="video/mp4"></video>';
        }

        return null;
    }

    /**
     * `w2a_mada_play()`'s own youtube-id extraction (`functions.php:826-832`):
     * `?v=` query param for a `youtube.com` link, or the path segment for a
     * `youtu.be` short-link.
     */
    private function youtubeId(string $link): string
    {
        if (str_contains($link, 'youtube')) {
            parse_str((string) parse_url($link, PHP_URL_QUERY), $vars);

            return (string) ($vars['v'] ?? '');
        }

        return str_replace('https://youtu.be/', '', $link);
    }
}
