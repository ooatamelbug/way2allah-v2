<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/group.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * `group.php` never sets `$ob->mode` either — same default
 * (IF-005-shaped) `ListKhotab()` branch as `KhotabSeriesController`. No
 * IF-015-shaped bug here: `group.php`'s `$Group` is always a real fetched
 * row (no `array()` fallback branch exists for groups), so its sidebar's
 * `$Group->author_id` use is always valid.
 */
class KhotabGroupController
{
    public function show(int $group, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $groupModel = KhotabGroup::where('hidden', 0)->findOrFail($group);

        $video = (bool) $groupModel->vedio;

        $series = $listing->seriesByAuthorAndGroup($groupModel->author_id, $group, $video);
        $items = $listing->khotabItemsDefault($groupModel->author_id, 0, $group, $video);
        $mostDownloaded = $sidebar->khotabMostDownloadedByAuthor($groupModel->author_id, $video);
        $mostRecent = $sidebar->khotabMostRecentByAuthor($groupModel->author_id, $video);
        $randomFeatured = $sidebar->khotabRandomFeatured();

        return view('khotab.group', compact('groupModel', 'series', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured'));
    }
}
