<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/series.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * `series.php` never sets `$ob->mode`, so `ListKhotab()`'s `else` (default,
 * IF-005-shaped unconditional-filter) branch is the one reached —
 * `ContentListingService::khotabItemsDefault()`, not the `'fixed'/'new'`
 * branch.
 *
 * IF-015's fix: both sidebar boxes use the series' own author
 * consistently (`series.php:146` incorrectly used `$Group->author_id`,
 * which is undefined when the series has no group — this controller
 * always uses the series' `author_id`, matching the "Newest" box's
 * already-correct behavior).
 *
 * Shared Page Chrome Parity Audit: `series.php:62-79`'s breadcrumb
 * ("المرئيات"/"الصوتيات" → "قائمة الدعاة" → author → optional group →
 * series name, all flat array entries, no DB ancestor walk) is now
 * restored via `pageHeading`/`breadcrumbTrail`. Deliberately NOT
 * reproduced: `series.php:80`'s extra `breadcrumb($breadcrumb, 1, true,
 * $Series->cat, 'series', $Series->id)` params, which trigger a SEPARATE
 * legacy mechanism — an inline `<div class="cat_tree">` category-ancestor
 * cloud appended after `</ul>` (functions.php:475-506), structurally
 * unrelated to the `page-breadcrumb` `<ul>` this batch's shared component
 * covers. Never implemented on any page in this migration (checked
 * `categories/series.blade.php`, the closest sibling with the same
 * `Ser_Cat_Breadcrumb`/`cat` column shape — also skips it). Flagged as a
 * distinct, deferred finding, not silently dropped.
 */
class KhotabSeriesController
{
    public function show(int $series, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $seriesModel = Series::where('hidden', 0)->findOrFail($series);

        $video = (bool) $seriesModel->vedio;

        $items = $listing->khotabItemsDefault($seriesModel->author_id, $series, $seriesModel->group_id, $video);
        $mostDownloaded = $sidebar->khotabMostDownloadedByAuthor($seriesModel->author_id, $video);
        $mostRecent = $sidebar->khotabMostRecentByAuthor($seriesModel->author_id, $video);
        $randomFeatured = $sidebar->khotabRandomFeatured();

        $op = $video ? 'video' : 'audio';
        $author = $seriesModel->author;
        $authorName = trim(($author->prename ?? '').' '.($author->name ?? ''));

        $breadcrumbTrail = [
            ['title' => $video ? 'المرئيات' : 'الصوتيات', 'url' => "/khotab-{$op}.htm"],
            ['title' => 'قائمة الدعاة', 'url' => "/khotab-{$op}.htm"],
            ['title' => $authorName, 'url' => "/khotab-{$op}-{$seriesModel->author_id}.htm"],
        ];

        if ($seriesModel->group_id > 0 && $seriesModel->group !== null) {
            $breadcrumbTrail[] = ['title' => 'مجموعة '.$seriesModel->group->title, 'url' => "/khotab-group-{$seriesModel->group_id}.htm"];
        }

        $breadcrumbTrail[] = ['title' => 'سلسلة '.$seriesModel->title, 'url' => ''];

        $pageHeading = 'سلسلة '.$seriesModel->title.' - '.$authorName;

        return view('khotab.series', compact('seriesModel', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured', 'breadcrumbTrail', 'pageHeading'));
    }
}
