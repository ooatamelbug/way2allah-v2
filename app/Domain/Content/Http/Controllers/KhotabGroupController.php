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
 *
 * Full Design Parity Pass (`khotab-group-{id}.htm`): `group.php:36-45`'s
 * real breadcrumb — a 4-item chain, video/audio-conditional, confirmed
 * live — was previously not built at all (no chrome/breadcrumb rendered).
 * Built here rather than in the view, matching this project's own
 * established `CategoryController::show()` precedent for breadcrumb
 * construction.
 */
class KhotabGroupController
{
    public function show(int $group, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $groupModel = KhotabGroup::where('hidden', 0)->with('author')->findOrFail($group);

        $video = (bool) $groupModel->vedio;
        $authorModel = $groupModel->author;

        $series = $listing->seriesByAuthorAndGroup($groupModel->author_id, $group, $video);
        $items = $listing->khotabItemsDefault($groupModel->author_id, 0, $group, $video);
        $mostDownloaded = $sidebar->khotabMostDownloadedByAuthor($groupModel->author_id, $video);
        $mostRecent = $sidebar->khotabMostRecentByAuthor($groupModel->author_id, $video);
        $randomFeatured = $sidebar->khotabRandomFeatured();

        // group.php:36-45 — video/audio-conditional 4-item breadcrumb.
        // Items 1 and 2 genuinely share the same URL in legacy source
        // (both `khotab-video.htm`/`khotab-audio.htm`) — confirmed live,
        // not a typo to "fix". The final item carries an explicit
        // `'url' => ''` (not an absent key) — isset() is true in legacy,
        // so it renders as a real, empty-href `<a href="">`, the same
        // established distinction already used for social.htm/recite.htm.
        $opLabel = $video ? 'المرئيات' : 'الصوتيات';
        $opUrl = $video ? '/khotab-video.htm' : '/khotab-audio.htm';
        $authorUrl = $video ? '/khotab-video-'.$groupModel->author_id.'.htm' : '/khotab-audio-'.$groupModel->author_id.'.htm';
        $authorName = trim(($authorModel->prename ?? '').' '.($authorModel->name ?? ''));
        $breadcrumbTrail = [
            ['title' => $opLabel, 'url' => $opUrl],
            ['title' => 'قائمة الدعاة', 'url' => $opUrl],
            ['title' => $authorName, 'url' => $authorUrl],
            ['title' => 'مجموعة '.$groupModel->title, 'url' => ''],
        ];

        return view('khotab.group', compact('groupModel', 'authorModel', 'authorName', 'series', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured', 'breadcrumbTrail'));
    }
}
