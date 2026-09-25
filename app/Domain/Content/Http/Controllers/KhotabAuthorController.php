<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
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
     * authors.php's alphabetical grouping, quick navigation, and
     * per-author video/audio/pdf count (`$Author->count`, the raw
     * `vedio`/`audio`/`pdf` column already loaded on each Author
     * model — not a separate aggregate query, matching authors.php's own
     * aliased-column SELECT). The view receives one grouped collection so
     * it can render semantic sections without server-generated HTML.
     * `ORDER BY BINARY name ASC` (authors.php:8)
     * reproduced via the same MySQL/SQLite driver-aware raw clause already
     * established by `LiveStreamController::titleOrderClause()`.
     *
     * **Fatwa Authors Batch 1 — the `fatwa` op no longer uses the stored
     * `nuke_islamic_authors.fatwa` column**, for either the displayed number
     * or directory membership. Production proved that column is not the
     * count of anything this site can display: author 17 stored `12` against
     * 126 real distinct questions, author 242 stored `289` against **zero**
     * mapping rows — so `/fatawa-authors.htm` advertised 289 fatwas for an
     * author whose page can only ever be empty. Nothing writes that column
     * anywhere (0 writes in either legacy admin module, `admincp/` or
     * `crons/`), so it cannot self-correct.
     *
     * Both the number and membership now come from
     * `ContentListingService::fatwaDistinctQuestionCountsByAuthor()` — the
     * SQL mirror of the author page's own id-parsing rule, so the directory
     * and the page it links to cannot disagree. Membership is
     * `hidden = 0 AND count > 0`.
     *
     * **`hidden = 0` is preserved unchanged** (`authors.php:24`); production
     * R-1c confirmed 0 authors with `hidden <> 0` have any fatwas, so no
     * previously-hidden author becomes visible. Measured production effect:
     * **51 -> 44 authors** (35 stale entries removed, 28 authors with real
     * content made discoverable), author 17's number **12 -> 126**.
     *
     * `/auther-questions-{id}.htm` remains a valid URL for every author,
     * including those removed from this directory — no redirect, no 404.
     *
     * The `video`/`audio`/`pdf` branches are **deliberately untouched**:
     * their `vedio`/`audio` counters *are* incrementally maintained by the
     * legacy khotab admin (`vedio=vedio+1`, `audio=audio-1`), so they are
     * not the same class of stale data and are out of this batch's scope.
     */
    public function index(string $op, ContentListingService $listing): View
    {
        $op = in_array($op, ['video', 'audio', 'pdf'], true) ? $op : 'fatwa';

        if ($op === 'fatwa') {
            return $this->fatwaIndex($listing);
        }

        $countColumn = self::COUNT_COLUMNS[$op];

        $authors = Author::where('hidden', 0)
            ->where($countColumn, '>', 0)
            ->orderByRaw($this->nameOrderClause())
            ->get();

        $groupedAuthors = $authors->groupBy(function (Author $author): string {
            $letter = mb_substr((string) $author->name, 0, 1, 'UTF-8');

            return $letter === 'ه' ? 'هـ' : $letter;
        });

        return view('khotab.authors', [
            'groupedAuthors' => $groupedAuthors,
            'op' => $op,
            'countColumn' => $countColumn,
            'countLabel' => self::COUNT_LABELS[$op],
            'sectionTitle' => self::SECTION_TITLES[$op],
            'breadcrumbLabel' => self::BREADCRUMB_LABELS[$op],
        ]);
    }

    /**
     * The `fatwa` op's own author source — see `index()`'s docblock for why
     * it cannot use the stored column.
     *
     * The derived count is attached to each model as
     * `fatwa_displayable_count` and handed to the view through the existing
     * `$countColumn` indirection (`:count="$author->{$countColumn}"`), so
     * **the Blade template is unchanged** — same card component, same
     * grouping, same `BINARY name` ordering, same `khotab-fatwa-{id}.htm`
     * link shape.
     *
     * Two queries, both cheap: the aggregate (~10ms on production, measured
     * 2026-09-24) and one keyed author fetch. Ordering and grouping are
     * applied after the join in PHP for the same reason
     * `fatwaDistinctQuestionCountsByAuthor()` avoids `SELECT ...*` with
     * `GROUP BY` — MariaDB rejects that shape under `ONLY_FULL_GROUP_BY`
     * (error 1055).
     */
    private function fatwaIndex(ContentListingService $listing): View
    {
        $counts = $listing->fatwaDistinctQuestionCountsByAuthor();

        $authors = Author::where('hidden', 0)
            ->whereIn('id', $counts->keys()->all())
            ->orderByRaw($this->nameOrderClause())
            ->get()
            ->each(function (Author $author) use ($counts): void {
                $author->fatwa_displayable_count = (int) $counts->get($author->id, 0);
            });

        $groupedAuthors = $authors->groupBy(function (Author $author): string {
            $letter = mb_substr((string) $author->name, 0, 1, 'UTF-8');

            return $letter === 'ه' ? 'هـ' : $letter;
        });

        return view('khotab.authors', [
            'groupedAuthors' => $groupedAuthors,
            'op' => 'fatwa',
            'countColumn' => 'fatwa_displayable_count',
            'countLabel' => self::COUNT_LABELS['fatwa'],
            'sectionTitle' => self::SECTION_TITLES['fatwa'],
            'breadcrumbLabel' => self::BREADCRUMB_LABELS['fatwa'],
        ]);
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
