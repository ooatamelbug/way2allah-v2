<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Models\W2acdItem;
use App\Domain\Content\Support\CdBanner;
use App\Domain\Content\Support\CdGroupIcon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Replaces `w2acd/cds.php` and `w2acd/item.php` — Roadmap task 4.5.
 *
 * IF-026: none of this module's pretty `cds-*.htm` URLs (`.htaccess`)
 * actually reach these two files — every one of them routes to
 * `new_modules.php`, which doesn't exist in this snapshot. These routes
 * are therefore registered at the exact raw legacy path
 * (`w2acd/cds.php`, `w2acd/item.php`, query-string parameters preserved)
 * rather than a new clean path + legacy-compat redirect — `Route::redirect()`
 * doesn't forward query strings, and both pages' real identity (`?id=`,
 * `?khid=`) lives entirely in the query string, not a path segment.
 *
 * IF-025's fix: the group filter actually works — the resolved `$id` is
 * used consistently for both the listing query and the hit-count update,
 * not silently zeroed by an assignment-in-argument typo.
 *
 * `hidden` is NOT filtered anywhere in this controller, matching
 * `w2acd/cds.php`'s and `w2acd/item.php`'s own queries exactly (confirmed
 * by direct reading — neither ever checks it) — see `W2acdItem`'s
 * docblock for why this is reproduced-as-found, not "fixed."
 *
 * The `most_downloaded_recent_sidebar($Group)`/`most_downloaded_list()`/
 * `most_recent_list()` sidebar (item.php's confirmed-dead `$Group`
 * parameter, `functions.php:181-246`) already had its `var-item-{id}.htm`
 * link bug identified in Wave 1 (`ContentSidebarWidget`'s own docblock,
 * P-016 §2) — this view uses the correct `cds-item-{id}.htm` prefix, not
 * the legacy bug.
 */
class W2acdController
{
    public function index(Request $request): View
    {
        $groupId = (int) $request->query('id', 0);
        $page = max(1, (int) $request->query('page', 1));

        $items = $this->releasesInGroup($groupId, $page);

        // OLD Group(0) → ListGroup(0) (W2ACD visitor parity W3, 2026-11-05): cds-main lists the top-level
        // groups (`parent_id = 0`, `id DESC`) above the general CDs — the same cards and query as a group
        // page's sub-groups; a group added by M4 «إضافة قسم فرعي» shows here after its redirect.
        $subGroups = $groupId === 0 ? $this->groupCards(0) : new Collection;

        // w2acd/cds.php:37 — the fixed version of IF-025: increments the
        // group actually being viewed, not group 0 unconditionally.
        if ($groupId > 0) {
            W2acdGroup::where('id', $groupId)->increment('hits');
        }

        $mostDownloaded = W2acdItem::orderByDesc('hits')->limit(6)->get(['id', 'title', 'hits', 'thumbnail']);
        $mostRecent = W2acdItem::orderByDesc('mytime')->limit(6)->get(['id', 'title', 'mytime', 'thumbnail']);

        return view('w2acd.index', compact('items', 'groupId', 'subGroups', 'mostDownloaded', 'mostRecent'));
    }

    /**
     * `khid` normally arrives as a query string (`/w2acd/item.php?khid=X`,
     * IF-026). Legacy-Source Reconstruction (`cds-item-{id}.htm`) adds a
     * second, direct route at the exact real pretty path with `khid` as a
     * path segment instead — not the `Route::redirect()` IF-026 already
     * ruled out (that can't carry a query string), and not a new clean
     * path either: `cds-main.htm`'s own reconstructed card grid already
     * generates real `/cds-item-{id}.htm` links (same G-11-01 precedent —
     * a real generated link needing to resolve), so this just completes
     * that already-existing reference, `cds-main.htm` itself untouched.
     */
    public function show(Request $request): View
    {
        $id = (int) ($request->route('khid') ?? $request->query('khid', 0));

        $w2acdItem = W2acdItem::findOrFail($id);

        // Post-Wave-4 fix (cross-wave review): was a hand-rolled
        // increment()+update() duplicating RecordsView's own logic — now
        // goes through the same ContentViewed/RecordsView pipeline as
        // every sibling content-item model.
        $w2acdItem->recordView();

        $mostDownloaded = W2acdItem::orderByDesc('hits')->limit(6)->get(['id', 'title', 'hits', 'thumbnail']);
        $mostRecent = W2acdItem::orderByDesc('mytime')->limit(6)->get(['id', 'title', 'mytime', 'thumbnail']);

        return view('w2acd.show', compact('w2acdItem', 'mostDownloaded', 'mostRecent'));
    }

    /**
     * W2ACD public admin parity, Phase 2 — OLD `Group($id, $page)`
     * (`modules/w2acd/index.php`, `cds-group-{id}.htm` / `cds-group-{id}-page-{n}.htm`,
     * `.htaccess:522-525`), in OLD's order: the `Logo()` breadcrumb
     * («الاسطوانات الدعوية» → ancestors → this group), the admin panel
     * («لوحة تحكم قسم الاسطوانات», admins only — `CdGroupControls`), the
     * sub-groups (`ListGroup()`: `parent_id = id ORDER BY id DESC`, only when
     * there are any) and the group's CDs (`ListW2ACD()`: `group_id = id ORDER BY
     * order_in_group DESC, mytime DESC`, 24 a page, only when there are any —
     * the same query `cds-main.htm` uses, with no `hidden` filter, as OLD's),
     * then `hits + 1`.
     *
     * Corrected, not reproduced: a group with no row is a 404 (OLD printed an
     * empty page); `cds-group-0.htm` — OLD's `Group(0)`, i.e. `cds-main` — goes
     * to `/cds-main.htm`; the sub-group card's CD count reads the group's CDs
     * (OLD printed `$Group[anasheed]`, a column this table lacks, so it was
     * always blank) and its sub-group count the group's sub-groups, rather than
     * the stored counters; pager links go to the `-page-{n}.htm` segment the
     * route reads. Not restored: the «تحميل سلسلة» box (`DownSeries()`, a
     * GetRight list of Anasheed `var-download-{id}.htm` links built from CD ids)
     * and ads position 9 — neither is part of this phase.
     */
    public function group(int $group, ?int $page = null): View|RedirectResponse
    {
        if ($group === 0) {
            return redirect()->to('/cds-main.htm', 301);
        }

        $groupModel = W2acdGroup::findOrFail($group);

        $subGroups = $this->groupCards($group);

        $items = $this->releasesInGroup($group, max(1, $page ?? 1));
        $items = new class(fn (int $n) => route('w2acd.group.paged', ['group' => $group, 'page' => $n]), $items->items(), $items->total(), $items->perPage(), $items->currentPage(), ['path' => $items->path()]) extends LengthAwarePaginator
        {
            /** @param  array<int, mixed>  $items  @param  array<string, string|null>  $options */
            public function __construct(private \Closure $urlFor, array $items, int $total, int $perPage, int $currentPage, array $options)
            {
                parent::__construct($items, $total, $perPage, $currentPage, $options);
            }

            public function url($page)
            {
                return ($this->urlFor)(max(1, (int) $page));
            }
        };

        $groupModel->increment('hits');

        $trail = $groupModel->breadcrumbTrail()->values();
        $last = $trail->count() - 1;
        $breadcrumb = [['title' => 'الاسطوانات الدعوية', 'url' => '/cds-main.htm']];
        foreach ($trail as $i => $node) {
            $breadcrumb[] = $i === $last
                ? ['title' => (string) $node->title, 'url' => '']
                : ['title' => (string) $node->title, 'url' => '/cds-group-'.$node->id.'.htm'];
        }

        return view('w2acd.group', compact('groupModel', 'subGroups', 'items', 'breadcrumb'));
    }

    /**
     * OLD `ListGroup($parentId)`'s cards — a group page's sub-groups and, for `0`, cds-main's top-level
     * groups (visitor parity W3): `parent_id = $parentId ORDER BY id DESC`, live sub-group / CD counts,
     * the card icon URL (or the default image).
     *
     * @return Collection<int, W2acdGroup>
     */
    private function groupCards(int $parentId): Collection
    {
        return W2acdGroup::query()
            ->where('parent_id', $parentId)
            ->withCount(['children', 'items'])
            ->orderByDesc('id')
            ->get()
            ->each(fn (W2acdGroup $sub) => $sub->setAttribute('icon_url', CdGroupIcon::forCard((int) $sub->id, (int) $sub->icon)));
    }

    /**
     * OLD `ListW2ACD()`'s rows — `cds-main.htm` (group 0) and `cds-group-{id}.htm`.
     *
     * @return LengthAwarePaginatorContract<int, W2acdItem>
     */
    private function releasesInGroup(int $groupId, int $page): LengthAwarePaginatorContract
    {
        return W2acdItem::where('group_id', $groupId)
            ->orderByDesc('order_in_group')
            ->orderByDesc('mytime')
            ->paginate(24, ['id', 'title', 'banner', 'thumbnail', 'group_id'], 'page', $page);
    }

    /**
     * W2ACD public admin parity, Phase 1 — OLD `Codes($id, $type)`
     * (`modules/w2acd/index.php:571-596`, `cds-code-{id}-{type}.htm`): for a
     * release with `banner = 1` and `hidden = 0`, the embed code of its banner
     * (`media/w2acd/banner/…/{id}.gif`, at its own size) — `3` phpBB, `2`
     * vBulletin, otherwise HTML. Public, as OLD's was; linked from the admin
     * banner page (OLD `banner.htm`).
     *
     * Corrected, not reproduced: only the three types the links use are
     * served (OLD rendered HTML for any other number) — as `khotab-code`;
     * a release without a servable banner is a 404 (OLD printed an empty
     * page, or a broken code when the file was missing).
     */
    public function bannerCode(int $khid, int $type): View
    {
        abort_unless(in_array($type, [1, 2, 3], true), 404);

        $release = W2acdItem::findOrFail($khid);
        $banner = CdBanner::for((int) $release->id, (int) $release->getAttribute('banner'), (int) $release->getAttribute('hidden'));
        abort_if($banner === null, 404);

        return view('w2acd.banner-code', [
            'release' => $release,
            'banner' => $banner,
            'type' => $type,
            'siteName' => (string) DB::connection('main')->table('nuke_config')->value('sitename'),
        ]);
    }
}
