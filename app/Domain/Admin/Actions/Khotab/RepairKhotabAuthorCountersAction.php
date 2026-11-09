<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;

/**
 * «صيانة العدادات» — OLD `KhotabCount` → `RepairCount()`
 * (`modules/Khotab/admin/index.php:3740-3836`, production working copy),
 * for THIS author only.
 *
 * OLD's writes for an author, reproduced in OLD's order:
 *
 * 1. `audio` = its items with `vedio = 0`, `vedio` = its items with
 *    `vedio = 1` — hidden items counted;
 * 2. for each of its series (`ORDER BY id`): when the series has a channel,
 *    its items with `channel_id = 0` take it; the series' `count` = all its
 *    items; all its items take the series' `group_id`;
 * 3. for each of its groups (`ORDER BY id`): `count` = all items in the group.
 *
 * Nothing else: not pdf, docx, advfile, location counts or letters.
 *
 * Owner correction (2026-10-21): OLD selected `WHERE id > Author_id`, so the
 * click repaired the NEXT author and a one-second refresh then swept every
 * higher id. Here the invoked author is repaired, once, and no other.
 * Authorized as `khotab.editauthor` (OLD checked nothing), one transaction,
 * audited with the before/after counters.
 */
final class RepairKhotabAuthorCountersAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return array{audio: int, vedio: int, series: int, groups: int} */
    public function execute(AdminUser $actor, Author $author, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.editauthor', $author);

        return DB::connection('main')->transaction(function () use ($actor, $author, $ip) {
            $result = $this->repair((int) $author->id);
            abort_if($result === null, 404);

            $this->audit->record($actor, 'repair_counts', 'khotab', 'author', (int) $author->id, [
                'audio' => $result['audio'],
                'vedio' => $result['vedio'],
                // Only the counts that changed, so a large author's audit row stays small.
                'series' => self::changed($result['series']),
                'groups' => self::changed($result['groups']),
            ], $ip);

            return ['audio' => $result['audio']['to'], 'vedio' => $result['vedio']['to'], 'series' => count($result['series']), 'groups' => count($result['groups'])];
        });
    }

    /**
     * OLD's writes for one author (see the class docblock), with no
     * authorization and no audit — the caller owns both and the transaction.
     * It is also the reference for Khotab Main's all-authors sweep
     * ({@see RepairAllKhotabAuthorCountersAction}), whose result must equal
     * this applied to every author in id order.
     *
     * @return array{audio: array{from: int, to: int}, vedio: array{from: int, to: int}, series: array<int, array{from: int, to: int}>, groups: array<int, array{from: int, to: int}>}|null null when the author does not exist
     */
    public function repair(int $authorId): ?array
    {
        $main = DB::connection('main');
        $row = $main->table('nuke_islamic_authors')->where('id', $authorId)->lockForUpdate()->first(['id', 'audio', 'vedio']);
        if ($row === null) {
            return null;
        }

        $items = fn () => $main->table('nuke_islamic_khotab');
        $audio = $items()->where('author', $row->id)->where('vedio', 0)->count();
        $vedio = $items()->where('author', $row->id)->where('vedio', 1)->count();
        $main->table('nuke_islamic_authors')->where('id', $row->id)->update(['audio' => $audio]);
        $main->table('nuke_islamic_authors')->where('id', $row->id)->update(['vedio' => $vedio]);

        $seriesChanges = [];
        foreach ($main->table('nuke_islamic_series')->where('author_id', $row->id)->orderBy('id')->get(['id', 'group_id', 'channel_id', 'count']) as $series) {
            if ((int) $series->channel_id > 0) {
                $items()->where('ser_id', $series->id)->where('channel_id', 0)->update(['channel_id' => (int) $series->channel_id]);
            }
            $count = $items()->where('ser_id', $series->id)->count();
            $main->table('nuke_islamic_series')->where('id', $series->id)->update(['count' => $count]);
            $items()->where('ser_id', $series->id)->update(['group_id' => (int) $series->group_id]);
            $seriesChanges[(int) $series->id] = ['from' => (int) $series->count, 'to' => $count];
        }

        $groupChanges = [];
        foreach ($main->table('nuke_islamic_groups')->where('author_id', $row->id)->orderBy('id')->get(['id', 'count']) as $group) {
            $count = $items()->where('group_id', $group->id)->count();
            $main->table('nuke_islamic_groups')->where('id', $group->id)->update(['count' => $count]);
            $groupChanges[(int) $group->id] = ['from' => (int) $group->count, 'to' => $count];
        }

        return [
            'audio' => ['from' => (int) $row->audio, 'to' => $audio],
            'vedio' => ['from' => (int) $row->vedio, 'to' => $vedio],
            'series' => $seriesChanges,
            'groups' => $groupChanges,
        ];
    }

    /**
     * @param  array<int, array{from: int, to: int}>  $changes
     * @return array<int, array{from: int, to: int}>
     */
    public static function changed(array $changes): array
    {
        return array_filter($changes, fn (array $c) => $c['from'] !== $c['to']);
    }
}
