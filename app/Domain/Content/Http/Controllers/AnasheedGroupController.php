<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;

/**
 * Replaces `anasheed/group.php` — Roadmap task 4.7. Public-only.
 *
 * `download_var_group_getright()` (the `.grx` GetRight-format group
 * download) is deliberately NOT ported — see `AnasheedItemController`'s
 * docblock.
 *
 * Group 98's special case (`AnasheedItem::scopeInGroup()`, extracted from
 * `anasheed/functions.php:312`'s `list_anasheed()`) is no longer a
 * mystery as of IF-029 (Wave 5): group 98 is `anasheed-news.htm`'s themed
 * aggregation target (`vars/more.php?id=98`, one of 4 real routes to an
 * otherwise-dead module — see `AnasheedNewsController`), and
 * `OR group_id='16'` is that theme's own business rule.
 */
class AnasheedGroupController
{
    public function show(int $group, ContentSidebarWidget $sidebar): View
    {
        $groupModel = AnasheedGroup::findOrFail($group);

        $subGroups = AnasheedGroup::where('parent_id', $group)->orderByDesc('id')->get();

        $items = $this->itemsForGroup($group);

        $mostDownloaded = $sidebar->anasheedMostDownloaded($group);
        $mostRecent = $sidebar->anasheedMostRecent($group);

        // anasheed/group.php:79 — group hit-count, unconditional on load.
        $groupModel->increment('hits');

        return view('anasheed.group', compact('groupModel', 'subGroups', 'items', 'mostDownloaded', 'mostRecent'));
    }

    private function itemsForGroup(int $groupId): LengthAwarePaginator
    {
        return AnasheedItem::inGroup($groupId)
            ->orderByDesc('mytime')->orderByDesc('order_in_group')
            ->paginate(30);
    }
}
