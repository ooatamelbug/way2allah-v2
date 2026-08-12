<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Replaces `anasheed/group.php` — Roadmap task 4.7. Public-only.
 *
 * `download_var_group_getright()` (`var-series-{id}.grx`,
 * `anasheed/group.php?op=down_serious`) is implemented below in
 * `downloadGetright()` — confirmed real, live `.htaccess` rule
 * (`.htaccess:100`) targeting this same file. Genuinely different from
 * `CategoryDownItemsController`'s khotab `.grx` generator (IF-040/041),
 * confirmed by direct reading of `anasheed/functions.php:266-304`, not
 * assumed from that sibling:
 * - The playlist `URL:` line points at this SITE'S OWN
 *   `var-download-{id}.htm` redirect, not the raw external `link` column
 *   directly (`functions.php:289`; the raw-`link` alternative is present
 *   but commented out in the source, `:290` — a real, deliberate choice,
 *   not dead code by accident).
 * - Folder path is 2 levels (`المنوعات\{group title}\`), not 1.
 * - **No `iconv()`/`windows-1256` re-encode at all** — `functions.php:301`
 *   is a bare `echo $Content;`. The `Content-Type` header itself instead
 *   reads `application/force-download; charset=utf-8, windows-1256` — a
 *   genuinely malformed multi-value charset parameter, reproduced
 *   verbatim, not corrected.
 * - No `hidden` filter on the underlying query at all.
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

    public function downloadGetright(int $group, ContentListingService $listing): Response
    {
        $groupModel = AnasheedGroup::findOrFail($group);

        $items = $listing->anasheedItemsForGroupDownload($group);

        $content = '';
        foreach ($items as $item) {
            $extension = strtolower((string) pathinfo((string) $item->link, PATHINFO_EXTENSION));
            $title = (string) $item->title;
            $title = str_replace('\\', '-', $title);
            $title = str_replace('/', '-', $title);
            $title = str_replace('*', ' ', $title);
            $title = str_replace('?', ' ', $title);
            $title = str_replace('<', ' ', $title);
            $title = str_replace('>', ' ', $title);
            $title = str_replace('|', ' ', $title);
            $title = str_replace('"', ' ', $title);
            $title = str_replace(':', '_', $title);

            $downloadUrl = url('/var-download-'.$item->id.'.htm');
            $content .= "URL: {$downloadUrl}\r\nFile: C:\\Way2Allah\\المنوعات\\{$groupModel->title}\\{$title}.{$extension}.GetRight\r\n\r\n";
        }

        return response($content, 200, [
            'Pragma' => 'public',
            'Expires' => '0',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0, private',
            'Content-Type' => 'application/force-download; charset=utf-8, windows-1256',
            'Content-Disposition' => 'attachment; filename="Way2Allah-Anasheed-'.$groupModel->id.'.grx"',
            'Content-Transfer-Encoding' => 'binary',
        ]);
    }

    private function itemsForGroup(int $groupId): LengthAwarePaginator
    {
        return AnasheedItem::inGroup($groupId)
            ->orderByDesc('mytime')->orderByDesc('order_in_group')
            ->paginate(30);
    }
}
