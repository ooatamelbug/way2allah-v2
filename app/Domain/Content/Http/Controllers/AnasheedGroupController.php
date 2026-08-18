<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\ContentListingService;
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
 *
 * G-12-01 (G-12 investigation): `var-group-{id}-page-{page}.htm` is a real,
 * live `.htaccess` rule (`.htaccess:98`) with real pagination-link
 * generation (`anasheed/functions.php:359-363`'s `PS_Pagination::
 * renderFullNav()`) — confirmed reachable, not dead. `group.php:8-9`
 * decrements `$page` exactly once before use (0-indexed offset math), the
 * same standard convention Laravel's own paginator uses 1-indexed — so the
 * captured `{page}` route segment is passed straight into `paginate()`'s
 * page argument with no adjustment. `$page` is nullable so the un-paginated
 * `/var-group-{id}.htm` route (no `{page}` segment) keeps its existing,
 * unchanged behavior of auto-resolving from a `?page=` query string.
 */
class AnasheedGroupController
{
    /**
     * G-13 closure (Anasheed Group Sidebar parity fix) — `group.php` (95
     * lines, read in full) calls exactly `list_sub_groups()`,
     * `download_var_group_getright_block()`, and `list_anasheed()`; none
     * of them, nor the shared `w2a_header()`/`w2a_footer()` wrappers,
     * ever call `most_downloaded_recent_sidebar($Group)` — confirmed by
     * exhaustively reading every function this page reaches. Unlike
     * `AnasheedItemController::show()` (`item.php:93` DOES call it), this
     * page never had a "most downloaded"/"most recent" sidebar. A prior
     * G-13 pass found `mostDownloaded`/`mostRecent` being computed and
     * rendered here anyway, with no citing comment — an unexplained
     * over-implementation, not a real legacy behavior. Removed.
     */
    public function show(int $group, ?int $page = null): View
    {
        $groupModel = AnasheedGroup::findOrFail($group);

        $subGroups = AnasheedGroup::where('parent_id', $group)->orderByDesc('id')->get();

        $items = $this->itemsForGroup($group, $page);

        // anasheed/group.php:79 — group hit-count, unconditional on load.
        $groupModel->increment('hits');

        return view('anasheed.group', compact('groupModel', 'subGroups', 'items'));
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

    private function itemsForGroup(int $groupId, ?int $page = null): LengthAwarePaginator
    {
        return AnasheedItem::inGroup($groupId)
            ->orderByDesc('mytime')->orderByDesc('order_in_group')
            ->paginate(30, ['*'], 'page', $page);
    }
}
