<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/authors.php` (directory) and `khotab/author.php` (one
 * author's page) — Roadmap task 4.1. Public-only, same scope decision as
 * `KhotabItemController` (see its docblock).
 */
class KhotabAuthorController
{
    private const COUNT_COLUMNS = ['video' => 'vedio', 'audio' => 'audio', 'pdf' => 'pdf'];

    /**
     * `khotab/authors.php` — one op-based author directory. The 4th,
     * `else` branch (`op` anything other than video/audio/pdf) filters on
     * `fatwa > 0` (Fact, `authors.php:24`) — the same `nuke_islamic_authors`
     * table doubles as the author list for the (separately-owned, per
     * Blueprint §7) `fatawa` module's video content. Reproduced here
     * exactly since it's the same table/column this controller already
     * queries, not new scope invented for `fatawa` itself.
     */
    public function index(string $op): View
    {
        $op = in_array($op, ['video', 'audio', 'pdf'], true) ? $op : 'fatwa';
        $countColumn = self::COUNT_COLUMNS[$op] ?? 'fatwa';

        $authors = Author::where('hidden', 0)
            ->where($countColumn, '>', 0)
            ->orderBy('name')
            ->get();

        return view('khotab.authors', compact('authors', 'op'));
    }

    /**
     * `khotab/author.php`. `$ob->mode` is never set by this file, so
     * `ListKhotab()`'s default (unconditional-filter, IF-005-shaped)
     * branch is always the one reached for video/audio ops —
     * `ContentListingService::khotabItemsDefault()`.
     *
     * IF-021's fix applied for the `pdf` op's sidebar (see that finding).
     *
     * Parameter order matters: Laravel's controller dispatcher binds route
     * parameters positionally, not by name (`ResolvesRouteDependencies::
     * resolveMethodDependencies()` calls `array_values($parameters)` before
     * matching, discarding the route's parameter names entirely) — `$op`
     * must come first here because it's captured first in the route
     * pattern `/khotab-{op}-{author}.htm`, regardless of what the
     * parameters are named. Got this wrong on the first pass (caught by
     * this method's own test, a real `TypeError` at request time, not a
     * silent bug) — worth remembering for every other multi-segment route
     * in the rest of Wave 4.
     */
    public function show(string $op, int $author, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $authorModel = Author::findOrFail($author);
        $op = in_array($op, ['video', 'audio'], true) ? $op : 'pdf';

        $groups = collect();
        $series = collect();
        $items = collect();

        if ($op === 'pdf') {
            $items = $listing->khotabPdfItemsByAuthor($author, 0, 0);
            $mostDownloaded = $sidebar->khotabMostDownloadedByAuthorForPdf($author);
            $mostRecent = $sidebar->khotabMostRecentByAuthorForPdf($author);
        } else {
            $video = $op === 'video';
            $groups = $listing->groupsByAuthor($author, $video);
            $series = $listing->seriesByAuthorAndGroup($author, 0, $video);
            $items = $listing->khotabItemsDefault($author, 0, 0, $video);
            $mostDownloaded = $sidebar->khotabMostDownloadedByAuthor($author, $video);
            $mostRecent = $sidebar->khotabMostRecentByAuthor($author, $video);
        }

        $randomFeatured = $sidebar->khotabRandomFeatured();

        return view('khotab.author', compact('authorModel', 'op', 'groups', 'series', 'items', 'mostDownloaded', 'mostRecent', 'randomFeatured'));
    }
}
