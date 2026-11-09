<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Advfile;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Series;
use App\Domain\Content\Support\SafeLegacyHtmlRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public promotional files (OLD Admin Migration, final public features,
 * owner decision 2026-10-15) — the working public half of OLD's
 * `modules/advfiles/index.php`; Phase 5 owns the administration.
 *
 * | OLD (URL / op) | Laravel |
 * |---|---|
 * | `khotab-advfile-{id}.htm` → `ShowAdFile($id, 0)` | `fragment()` — the bare file, for the item page's popup |
 * | `khotab-advfilefull-{id}.htm` → `ShowAdFile($id, 1)` | `full()` — the file inside the site layout |
 * | `khotab-advfiles.htm` → `MainPage()` | `library()` — preachers with promotional files |
 * | `op=Author` / `op=Khotab` → `Author()` | `author()` — `/khotab-advfiles-author-{id}.htm` |
 * | `op=Group` → `Group()` | `group()` — `/khotab-advfiles-group-{id}.htm` |
 * | `op=Series` → `Series()` | `series()` — `/khotab-advfiles-series-{id}.htm` |
 *
 * The sub-pages had no rewrite in OLD (`modules.php?name=advfiles&op=…`, and
 * Laravel has no `modules.php`), so they get pretty paths; the three rewritten
 * URLs keep their exact paths.
 *
 * **Not migrated (dead or broken in OLD):** `Detailes` (its payload query uses
 * a nonexistent `khid` column, and it plays through a RealPlayer `<embed>`);
 * `KhotabSearch` (reachable only from dead "Copy of" templates, and its results
 * link to `Detailes`); `MoreVedio`, `MoreAudio`, `AuthorVedio` (no handler);
 * `Repair` (admin counter repair — DEAD by plan §2 row 14: Phase 5 keeps the
 * counters right in the same transaction); the Flash audio player.
 *
 * **Corrections:**
 * - the stored `code` is rendered by `SafeLegacyHtmlRenderer`, never raw;
 * - lists skip hidden authors, groups, series and items. OLD did not filter, but
 *   their public pages 404 in Laravel, so listing them would only lead nowhere.
 *
 * Listing semantics are OLD's: the `advfile` counters Phase 5 maintains on
 * authors, groups and series select and count the rows; a series is listed
 * only while its own `count` > 0; items are ordered by `weight` DESC.
 */
class PromotionalFilesController
{
    public function fragment(int $id, SafeLegacyHtmlRenderer $renderer): View
    {
        return view('advfiles.fragment', $this->file($id, $renderer));
    }

    public function full(int $id, SafeLegacyHtmlRenderer $renderer): View
    {
        return view('advfiles.full', $this->file($id, $renderer));
    }

    public function library(): View
    {
        $authors = Author::query()
            ->select(['id', 'name', 'prename', 'advfile'])
            ->where('advfile', '>', 0)
            ->where('hidden', 0)
            ->orderByRaw(DB::connection('main')->getDriverName() === 'mysql' ? 'BINARY name ASC' : 'name ASC')
            ->get();

        return view('advfiles.library', ['authors' => $authors]);
    }

    public function author(int $id): View
    {
        $author = Author::query()->where('hidden', 0)->findOrFail($id);

        return view('advfiles.listing', [
            'heading' => 'الملفات الدعائية لـ '.trim($author->prename.' '.$author->name),
            'breadcrumb' => [['title' => 'الملفات الدعائية', 'url' => '/khotab-advfiles.htm'], ['title' => trim($author->prename.' '.$author->name), 'url' => '']],
            'groups' => KhotabGroup::query()->where('author_id', $id)->where('advfile', '>', 0)->where('hidden', 0)->orderByDesc('id')->get(['id', 'title', 'advfile']),
            'series' => $this->seriesOf($id, 0),
            'items' => $this->itemsOf($id, 0, 0),
        ]);
    }

    public function group(int $id): View
    {
        $group = KhotabGroup::query()->where('hidden', 0)->findOrFail($id);
        $author = Author::query()->find((int) $group->author_id);
        $authorName = $author ? trim($author->prename.' '.$author->name) : '';

        return view('advfiles.listing', [
            'heading' => 'مجموعة '.$group->title.($authorName !== '' ? ' - '.$authorName : ''),
            'breadcrumb' => array_values(array_filter([
                ['title' => 'الملفات الدعائية', 'url' => '/khotab-advfiles.htm'],
                $author ? ['title' => $authorName, 'url' => "/khotab-advfiles-author-{$author->id}.htm"] : null,
                ['title' => 'مجموعة '.$group->title, 'url' => ''],
            ])),
            'groups' => new Collection,
            'series' => $this->seriesOf((int) $group->author_id, $id),
            'items' => $this->itemsOf((int) $group->author_id, 0, $id),
        ]);
    }

    public function series(int $id): View
    {
        $series = Series::query()->where('hidden', 0)->findOrFail($id);
        $author = Author::query()->find((int) $series->author_id);
        $authorName = $author ? trim($author->prename.' '.$author->name) : '';

        return view('advfiles.listing', [
            'heading' => 'سلسلة '.$series->title.($authorName !== '' ? ' - '.$authorName : ''),
            'breadcrumb' => array_values(array_filter([
                ['title' => 'الملفات الدعائية', 'url' => '/khotab-advfiles.htm'],
                $author ? ['title' => $authorName, 'url' => "/khotab-advfiles-author-{$author->id}.htm"] : null,
                ['title' => 'سلسلة '.$series->title, 'url' => ''],
            ])),
            'groups' => new Collection,
            'series' => new Collection,
            'items' => $this->itemsOf((int) $series->author_id, $id, (int) $series->group_id),
        ]);
    }

    /** @return array{file: Advfile, item: KhotabItem|null, html: string} */
    private function file(int $id, SafeLegacyHtmlRenderer $renderer): array
    {
        // OLD read the file by its id alone (`WHERE id = $id`), as here.
        $file = Advfile::query()->findOrFail($id);

        return [
            'file' => $file,
            'item' => KhotabItem::query()->select(['id', 'title'])->find($id),
            'html' => $renderer->render($file->getAttribute('code'), $file->getAttribute('audio_url')),
        ];
    }

    /** OLD `ListSeries()`: listed only while the series' own `count` > 0. @return Collection<int, Series> */
    private function seriesOf(int $authorId, int $groupId): Collection
    {
        return Series::query()
            ->where('author_id', $authorId)->where('group_id', $groupId)
            ->where('advfile', '>', 0)->where('count', '>', 0)->where('hidden', 0)
            ->orderByDesc('id')->get(['id', 'title', 'advfile']);
    }

    /** OLD `ListKhotab()`. @return Collection<int, KhotabItem> */
    private function itemsOf(int $authorId, int $seriesId, int $groupId): Collection
    {
        return KhotabItem::query()
            ->where('author', $authorId)->where('ser_id', $seriesId)->where('group_id', $groupId)
            ->where('advfile', '>', 0)->where('hidden', 0)
            ->tap(fn (Builder $query) => $query->orderByDesc('weight')->orderBy('id'))
            ->get(['id', 'title']);
    }
}
