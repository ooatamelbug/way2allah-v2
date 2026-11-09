<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * «إصــــلاح الـــعــدادات» on Khotab Main — OLD `KhotabCount` →
 * `RepairCount()` with no author (`modules/Khotab/admin/index.php:3740`):
 * OLD repaired every author, from the lowest id up, one author per page and a
 * one-second meta refresh to the next, until none was left.
 *
 * The result is the approved per-author repair
 * ({@see RepairKhotabAuthorCountersAction::repair()}) applied to every author:
 * audio / vedio from all their items (hidden included); for each of their
 * series the channel onto channel-less items, the count from all its items,
 * the group onto all its items; for each of their groups the count. Only
 * series and groups whose author exists are touched, as OLD reached them
 * through the author. Nothing else (pdf, docx, advfile, location counts,
 * letters).
 *
 * Owner decision (2026-10-21): one server-side operation instead of OLD's
 * browser-driven chain. It is computed set-wise — one grouped count per
 * table, the propagations as chunked updates, and only rows whose value
 * changes written — because `nuke_islamic_khotab` (~290k rows) has no index
 * on `ser_id` / `group_id`, and the per-author form costs three full scans per
 * series (14k+ series: far beyond any request). The tests prove the result
 * equals running the per-author repair for every author in id order. One
 * difference is possible only in a corner OLD's order made inconsistent: a
 * group's count is taken after every series has propagated its group, where
 * OLD took it part-way through (before later authors' series were done).
 *
 * One transaction; each author whose stored values changed gets its own
 * `repair_counts` audit row (marked `sweep`). Authorized as
 * `khotab.editauthor` (OLD checked nothing).
 */
final class RepairAllKhotabAuthorCountersAction
{
    private const CHUNK = 1000;

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{authors: int, authors_changed: int, series: int, series_changed: int, groups: int, groups_changed: int} */
    public function execute(AdminUser $actor, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.editauthor');

        return DB::connection('main')->transaction(function () use ($actor, $ip) {
            $main = DB::connection('main');
            $items = fn () => $main->table('nuke_islamic_khotab');

            // 1. audio / vedio — every item of the author, hidden included.
            $authors = $main->table('nuke_islamic_authors')->orderBy('id')->lockForUpdate()->get(['id', 'audio', 'vedio'])->keyBy('id');
            $byAuthor = [];
            foreach ($items()->whereIn('vedio', [0, 1])->groupBy('author', 'vedio')->select('author', 'vedio', DB::raw('COUNT(*) as n'))->get() as $row) {
                $byAuthor[(int) $row->author][(int) $row->vedio] = (int) $row->n;
            }
            $changes = [];
            foreach ($authors as $id => $author) {
                $audio = $byAuthor[$id][0] ?? 0;
                $vedio = $byAuthor[$id][1] ?? 0;
                if ($audio !== (int) $author->audio || $vedio !== (int) $author->vedio) {
                    $main->table('nuke_islamic_authors')->where('id', $id)->update(['audio' => $audio, 'vedio' => $vedio]);
                    $changes[$id]['audio'] = ['from' => (int) $author->audio, 'to' => $audio];
                    $changes[$id]['vedio'] = ['from' => (int) $author->vedio, 'to' => $vedio];
                }
            }

            // The series and groups OLD reached: those whose author exists.
            $series = $main->table('nuke_islamic_series as s')->join('nuke_islamic_authors as a', 'a.id', '=', 's.author_id')
                ->orderBy('s.id')->get(['s.id', 's.author_id', 's.group_id', 's.channel_id', 's.count']);

            // 2. a series' channel onto its items that have none.
            foreach ($series->filter(fn ($s) => (int) $s->channel_id > 0)->chunk(self::CHUNK) as $chunk) {
                $items()->whereIn('ser_id', $chunk->pluck('id')->all())->where('channel_id', 0)
                    ->update(['channel_id' => DB::raw(self::caseOf($chunk, 'channel_id'))]);
            }

            // 3. a series' count from all its items; 4. its group onto all its items.
            $seriesCounts = $items()->where('ser_id', '>', 0)->groupBy('ser_id')->select('ser_id', DB::raw('COUNT(*) as n'))->pluck('n', 'ser_id');
            $seriesChanged = 0;
            foreach ($series as $s) {
                $count = (int) ($seriesCounts[$s->id] ?? 0);
                if ($count !== (int) $s->count) {
                    $main->table('nuke_islamic_series')->where('id', $s->id)->update(['count' => $count]);
                    $changes[(int) $s->author_id]['series'][(int) $s->id] = ['from' => (int) $s->count, 'to' => $count];
                    $seriesChanged++;
                }
            }
            foreach ($series->chunk(self::CHUNK) as $chunk) {
                $items()->whereIn('ser_id', $chunk->pluck('id')->all())
                    ->update(['group_id' => DB::raw(self::caseOf($chunk, 'group_id'))]);
            }

            // 5. a group's count from all items in it.
            $groups = $main->table('nuke_islamic_groups as g')->join('nuke_islamic_authors as a', 'a.id', '=', 'g.author_id')
                ->orderBy('g.id')->get(['g.id', 'g.author_id', 'g.count']);
            $groupCounts = $items()->where('group_id', '>', 0)->groupBy('group_id')->select('group_id', DB::raw('COUNT(*) as n'))->pluck('n', 'group_id');
            $groupsChanged = 0;
            foreach ($groups as $g) {
                $count = (int) ($groupCounts[$g->id] ?? 0);
                if ($count !== (int) $g->count) {
                    $main->table('nuke_islamic_groups')->where('id', $g->id)->update(['count' => $count]);
                    $changes[(int) $g->author_id]['groups'][(int) $g->id] = ['from' => (int) $g->count, 'to' => $count];
                    $groupsChanged++;
                }
            }

            ksort($changes);
            $authorsChanged = 0;
            foreach ($changes as $authorId => $change) {
                $authorsChanged += isset($change['audio']) ? 1 : 0;
                $this->audit->record($actor, 'repair_counts', 'khotab', 'author', $authorId, ['sweep' => true] + $change, $ip);
            }

            return [
                'authors' => $authors->count(),
                'authors_changed' => $authorsChanged,
                'series' => $series->count(),
                'series_changed' => $seriesChanged,
                'groups' => $groups->count(),
                'groups_changed' => $groupsChanged,
            ];
        });
    }

    /**
     * `CASE ser_id WHEN … THEN … END` for a chunk of series — integers only,
     * cast here, so the expression carries no user input.
     *
     * @param  iterable<object>  $series
     */
    private static function caseOf(iterable $series, string $column): string
    {
        $case = 'CASE ser_id';
        foreach ($series as $s) {
            $case .= ' WHEN '.(int) $s->id.' THEN '.(int) $s->{$column};
        }

        return $case.' END';
    }
}
