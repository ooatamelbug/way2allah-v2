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
 *
 * Superseded for selection (visitor parity A1, 2026-11-04): `vars/more.php` is Gen 2. OLD
 * `modules/Anasheed` `MoreAnasheed()` / `ListNews()` governs — see `show()`.
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

        // OLD MoreAnasheed() / ListNews() (visitor parity A1, 2026-11-04): the whole department —
        // items whose `parent_id` (the root group, set on insert) is this one, 98 also taking 16 —
        // every pinned item, then the newest 24, both `ORDER BY mytime DESC`.
        $department = $groupId === 98 ? [98, 16] : [$groupId];

        $pinnedItems = AnasheedItem::whereIn('parent_id', $department)
            ->where('fixed', 1)
            ->orderByDesc('mytime')
            ->get();

        $newestItems = AnasheedItem::whereIn('parent_id', $department)
            ->orderByDesc('mytime')
            ->limit(24)
            ->get();

        return view('anasheed.news', compact('group', 'pinnedItems', 'newestItems'));
    }
}
