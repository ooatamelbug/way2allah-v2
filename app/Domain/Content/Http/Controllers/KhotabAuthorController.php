<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Replaces `khotab/authors.php` (directory) and `khotab/author.php` (one
 * author's page) — Roadmap task 4.1. Public-only, same scope decision as
 * `KhotabItemController` (see its docblock).
 */
class KhotabAuthorController
{
    private const COUNT_COLUMNS = ['video' => 'vedio', 'audio' => 'audio', 'pdf' => 'pdf'];

    /** authors.php:7,13,18,23 — the `<title>` tag text, one per op. */
    private const SECTION_TITLES = [
        'video' => 'قسم المرئيات',
        'audio' => 'قسم الصوتيات',
        'pdf' => 'قسم المواد المفرغة',
        'fatwa' => 'قسم الفتاوى المرئية',
    ];

    /** authors.php:9,15,20,25 — first breadcrumb segment, one per op. */
    private const BREADCRUMB_LABELS = [
        'video' => 'المرئيات',
        'audio' => 'الصوتيات',
        'pdf' => 'المواد المفرغة',
        'fatwa' => 'الفتاوى المرئية',
    ];

    /** authors.php:10,16,21,26 — the per-author count suffix word. */
    private const COUNT_LABELS = [
        'video' => 'فيديو',
        'audio' => 'صوت',
        'pdf' => 'منشور',
        'fatwa' => 'فتوى',
    ];

    /**
     * `khotab/authors.php` — one op-based author directory. The 4th,
     * `else` branch (`op` anything other than video/audio/pdf) filters on
     * `fatwa > 0` (Fact, `authors.php:24`) — the same `nuke_islamic_authors`
     * table doubles as the author list for the (separately-owned, per
     * Blueprint §7) `fatawa` module's video content. Reproduced here
     * exactly since it's the same table/column this controller already
     * queries, not new scope invented for `fatawa` itself.
     *
     * Visual parity audit (khotab-video.htm, 2026-08-18): reproduces
     * authors.php's alphabetical grouping (lines 58-74), A-Z jump nav
     * (lines 69-73, populated client-side exactly like legacy), and
     * per-author video/audio/pdf/fatwa count (`$Author->count`, the raw
     * `vedio`/`audio`/`pdf`/`fatwa` column already loaded on each Author
     * model — not a separate aggregate query, matching authors.php's own
     * aliased-column SELECT). `ORDER BY BINARY name ASC` (authors.php:8)
     * reproduced via the same MySQL/SQLite driver-aware raw clause already
     * established by `LiveStreamController::titleOrderClause()`.
     */
    public function index(string $op): View
    {
        $op = in_array($op, ['video', 'audio', 'pdf'], true) ? $op : 'fatwa';
        $countColumn = self::COUNT_COLUMNS[$op] ?? 'fatwa';

        $authors = Author::where('hidden', 0)
            ->where($countColumn, '>', 0)
            ->orderByRaw($this->nameOrderClause())
            ->get();

        [$rows, $letterListHtml] = $this->groupedAuthorRows($authors);

        return view('khotab.authors', [
            'rows' => $rows,
            'op' => $op,
            'countColumn' => $countColumn,
            'countLabel' => self::COUNT_LABELS[$op],
            'sectionTitle' => self::SECTION_TITLES[$op],
            'breadcrumbLabel' => self::BREADCRUMB_LABELS[$op],
            'letterListHtml' => $letterListHtml,
        ]);
    }

    /**
     * authors.php:58-74,69-73. `$Char1` is the author's first UTF-8
     * character (`ه` normalized to `هـ`, matching the source's own special
     * case exactly); a new group starts whenever it differs from the
     * previous author's. `$X` (here: `index`) is the author's own position
     * in the overall ordered list, not a per-letter counter — the A-Z
     * nav's anchors (`#0`, `#77`, `#93`, ...) and each group's `<h1 id="">`
     * both key off this same running index, so they must be built in one
     * pass together (exactly as authors.php itself does) rather than
     * derived independently.
     *
     * @return array{0: array<int, object{author: Author, index: int, groupLetter: ?string}>, 1: string}
     */
    private function groupedAuthorRows(Collection $authors): array
    {
        $rows = [];
        $letterListParts = [];
        $currentChar = null;
        $index = 0;

        foreach ($authors as $author) {
            $char1 = mb_substr($author->name, 0, 1, 'UTF-8');
            if ($char1 === 'ه') {
                $char1 = 'هـ';
            }

            $groupLetter = null;
            if ($char1 !== $currentChar) {
                $currentChar = $char1;
                $groupLetter = $char1;
                $letterListParts[] = '<a href="#'.$index.'">'.$char1.'</a>';
            }

            $rows[] = (object) [
                'author' => $author,
                'index' => $index,
                'groupLetter' => $groupLetter,
            ];

            $index++;
        }

        return [$rows, implode('&nbsp;-&nbsp;', $letterListParts)];
    }

    /**
     * authors.php:8 — `ORDER BY BINARY name ASC`. Same driver-aware
     * reasoning as `LiveStreamController::titleOrderClause()`: MySQL's
     * `BINARY` cast keyword is a syntax error against SQLite (the test
     * suite's connection), so it's only applied against the real driver —
     * not a production behavior change.
     */
    private function nameOrderClause(): string
    {
        return DB::connection('main')->getDriverName() === 'sqlite'
            ? 'name ASC'
            : 'BINARY name ASC';
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

        return view('khotab.author', [
            'authorModel' => $authorModel,
            'op' => $op,
            'groups' => $groups,
            'series' => $series,
            'items' => $items,
            'mostDownloaded' => $mostDownloaded,
            'mostRecent' => $mostRecent,
            'randomFeatured' => $randomFeatured,
            'opTitle' => self::BREADCRUMB_LABELS[$op],
            'pageTitle' => $this->authorPageTitle($op, $authorModel),
        ]);
    }

    /**
     * Visual parity audit (khotab-video-17.htm, 2026-08-18) Batch 1:
     * author.php:12-25's `$title` (the `<h3 class="page-title">` text,
     * via `title($title)` at :56) — one phrase shape per op, each
     * concatenating the author's `prename`/`name` exactly as legacy does
     * (including the literal double space in the `pdf` branch's
     * `'المواد المفرغة لـ  '` — not a typo to clean up, reproduced as-is).
     */
    private function authorPageTitle(string $op, Author $author): string
    {
        $prename = $author->prename ?? '';
        $name = $author->name ?? '';

        return match ($op) {
            'video' => 'مرئيات '.$prename.' '.$name,
            'audio' => 'صوتيات '.$prename.' '.$name,
            default => 'المواد المفرغة لـ  '.$prename.' '.$name,
        };
    }
}
