<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Contracts\View\View;

/**
 * Replaces `vars/more.php` — Roadmap task 5.1 (Wave 5). Fixes IF-029: the
 * legacy file calls an undefined function (`listanasheed()`, no
 * underscore — a typo for the real `list_anasheed()`) and 500s on every
 * request, despite backing 4 real, live `.htaccess` routes
 * (`exclusive-news.htm`, `cartoon-news.htm`, `documentary-news.htm`,
 * `anasheed-news.htm`), each a themed "pinned + newest items" view scoped
 * to one hardcoded `nuke_anasheed_groups` id.
 *
 * Reproduces `more.php`'s two `listanasheed()` calls using the real,
 * correctly-defined `list_anasheed()` behavior (already ported as
 * `AnasheedItem::scopeInGroup()` + the ordering `AnasheedGroupController`
 * already established): a "pinned" list (`fixed=1`, limit 100) and a
 * "newest" list (no fixed filter, limit 32) — matching `more.php:19-24`
 * and `:34-38`'s `$arr` parameters exactly.
 */
class AnasheedNewsController
{
    /** `.htaccess`'s 4 hardcoded `vars/more.php?id=N` targets — confirmed, not guessed (see IF-029). */
    private const THEME_GROUPS = [
        'exclusive' => 158,
        'cartoon' => 57,
        'documentary' => 12,
        'anasheed' => 98,
    ];

    public function show(string $theme): View
    {
        abort_unless(isset(self::THEME_GROUPS[$theme]), 404);

        $groupId = self::THEME_GROUPS[$theme];
        $group = AnasheedGroup::findOrFail($groupId);

        $pinnedItems = AnasheedItem::inGroup($groupId)
            ->where('fixed', 1)
            ->orderByDesc('mytime')->orderByDesc('order_in_group')
            ->limit(100)
            ->get();

        $newestItems = AnasheedItem::inGroup($groupId)
            ->orderByDesc('mytime')->orderByDesc('order_in_group')
            ->limit(32)
            ->get();

        return view('anasheed.news', compact('group', 'pinnedItems', 'newestItems'));
    }
}
