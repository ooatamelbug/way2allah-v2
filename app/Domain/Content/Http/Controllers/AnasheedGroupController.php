<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Support\VarietyMediaLink;
use App\Support\Http\AdminViewer;
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
 * Group 98's Gen 2 special case (`list_anasheed()`'s `OR group_id='16'`) is
 * not reproduced on the group page (visitor parity A4, 2026-11-04): OLD's
 * `ListAnasheed()` lists only the group's own items; 98 + 16 belongs to the
 * `anasheed-news.htm` department view (`AnasheedNewsController`).
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
    public function show(int $group, AdminViewer $contextual, ContentListingService $listing, ?int $page = null): View
    {
        $groupModel = AnasheedGroup::findOrFail($group);

        $subGroups = AnasheedGroup::where('parent_id', $group)->orderByDesc('id')->get();

        // F08 / B4: each card shows its PUBLICLY VISIBLE item count (one aggregate query), not the stored counter.
        $subGroupItemCounts = $listing->anasheedVisibleItemCountsByGroup($subGroups->pluck('id')->map(fn ($id) => (int) $id)->all());

        // F08 / B4: hidden items are listed only to `anasheed.editanasheed` holders and super-admins (marked
        // «مخفية»), so their controls stay reachable; that response is private. Everyone else: not listed.
        $admin = $contextual->can(AnasheedItemController::HIDDEN_PREVIEW_PERMISSION);
        $items = $this->itemsForGroup($group, $page, $admin);
        if ($admin && collect($items->items())->contains(fn ($item) => (int) $item->hidden !== 0)) {
            $contextual->markRendered();
        }

        // anasheed/group.php:79 — group hit-count, unconditional on load.
        $groupModel->increment('hits');

        // Shared Page Chrome Parity Audit: group.php:22-26's breadcrumb —
        // reuses the already-existing AnasheedGroup::breadcrumbTrail()
        // (built for var-item-{id}.htm's own ancestor chain, same
        // parent_id walk), ancestors linked to /var-group-{id}.htm, the
        // current group plain (group.php:26 — no `url` key at all).
        $ancestorTrail = $groupModel->breadcrumbTrail();
        $lastIndex = $ancestorTrail->count() - 1;
        $breadcrumbTrail = $ancestorTrail->values()->map(fn (AnasheedGroup $g, int $i) => $i === $lastIndex
            ? ['title' => $g->title]
            : ['title' => $g->title, 'url' => '/var-group-'.$g->id.'.htm'])->all();

        $showHiddenMarker = $admin;

        return view('anasheed.group', compact('groupModel', 'subGroups', 'subGroupItemCounts', 'items', 'breadcrumbTrail', 'showHiddenMarker'));
    }

    public function downloadGetright(int $group, ContentListingService $listing): Response
    {
        $groupModel = AnasheedGroup::findOrFail($group);

        $items = $listing->anasheedItemsForGroupDownload($group);

        $content = '';
        foreach ($items as $item) {
            // B2 / F07-B2 (decision-log #148): only items /var-download can actually serve (the F07-A rule); a broken
            // link would make GetRight save this site's 404 page or home page under the item's name.
            if (! VarietyMediaLink::isDownloadable($item->link)) {
                continue;
            }
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
            // OLD DownSeries(): the GetRight folder is «الأناشيد» (visitor parity A8, 2026-11-04).
            $content .= "URL: {$downloadUrl}\r\nFile: C:\\Way2Allah\\الأناشيد\\{$groupModel->title}\\{$title}.{$extension}.GetRight\r\n\r\n";
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

    /**
     * OLD ListAnasheed() (visitor parity A3 / A4 / A7, 2026-11-04): this group's own items only — no
     * 98 / 16 merge, which OLD applies only to the news pages — ordered `order_in_group DESC,
     * mytime DESC`, 24 per page.
     */
    private function itemsForGroup(int $groupId, ?int $page = null, bool $includeHidden = false): LengthAwarePaginator
    {
        return AnasheedItem::query()
            ->leftJoin('nuke_anasheed_advanced as ad', 'nuke_anasheed_anasheed.id', '=', 'ad.id')
            ->select(['nuke_anasheed_anasheed.*', 'ad.adur'])
            ->where('nuke_anasheed_anasheed.group_id', $groupId)
            ->when(! $includeHidden, fn ($query) => $query->where('nuke_anasheed_anasheed.hidden', 0))
            ->orderByDesc('nuke_anasheed_anasheed.order_in_group')
            ->orderByDesc('nuke_anasheed_anasheed.mytime')
            ->paginate(24, ['*'], 'page', $page);
    }
}
