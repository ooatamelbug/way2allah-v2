<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Models\W2acdItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
        $perPage = 24;

        $items = W2acdItem::where('group_id', $groupId)
            ->orderByDesc('order_in_group')
            ->orderByDesc('mytime')
            ->paginate($perPage, ['id', 'title', 'banner', 'thumbnail', 'group_id'], 'page', $page);

        // w2acd/cds.php:37 — the fixed version of IF-025: increments the
        // group actually being viewed, not group 0 unconditionally.
        if ($groupId > 0) {
            W2acdGroup::where('id', $groupId)->increment('hits');
        }

        $mostDownloaded = W2acdItem::orderByDesc('hits')->limit(6)->get(['id', 'title', 'hits', 'thumbnail']);
        $mostRecent = W2acdItem::orderByDesc('mytime')->limit(6)->get(['id', 'title', 'mytime', 'thumbnail']);

        return view('w2acd.index', compact('items', 'groupId', 'mostDownloaded', 'mostRecent'));
    }

    public function show(Request $request): View
    {
        $id = (int) $request->query('khid', 0);

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
}
