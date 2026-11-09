<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use Illuminate\Support\Facades\DB;

/**
 * «تعديل المجموعة» — OLD `KhotabEditGroup` → `ConfirmEditGroup`
 * (`modules/Khotab/admin/index.php:2710-2824`, production working copy).
 *
 * Writes exactly OLD's columns on `nuke_islamic_groups` — title,
 * description, meta_keywords, meta_description, meta_index, meta_follow,
 * hidden, channel_id, des — then cascades `channel_id` to every series and
 * every item `WHERE group_id = X`, as OLD did. An unknown channel is refused
 * (OLD died with «القناة غير موجودة بقواعد البيانات»); the request validates
 * it before this runs.
 *
 * Corrections, as in every earlier phase: OLD's `GreenZone('editgroup')` was
 * inert and its writes were three separate statements. Laravel authorizes
 * (`khotab.editgroup`) and commits the group, the cascade and the audit row
 * in one transaction. OLD's audio/video moderator split (`AvAdmin`) is not
 * reproduced (owner decision, 2026-10-12).
 */
final class UpdateKhotabGroupAction
{
    private const TEXT = ['title', 'description', 'meta_keywords', 'meta_description', 'des'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{title?: ?string, description?: ?string, meta_keywords?: ?string, meta_description?: ?string, meta_index?: mixed, meta_follow?: mixed, C1?: mixed, Channel?: int|string|null, Des?: ?string}  $data
     */
    public function execute(AdminUser $actor, KhotabGroup $group, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.editgroup', $group);

        DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $channel = (int) ($data['Channel'] ?? 0);
            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'hidden' => empty($data['C1']) ? 0 : 1,
                'channel_id' => $channel,
                'des' => (string) ($data['Des'] ?? ''),
            ];

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_islamic_groups')->where('id', $group->id)->update($after);
            $series = $main->table('nuke_islamic_series')->where('group_id', $group->id)->update(['channel_id' => $channel]);
            $items = $main->table('nuke_islamic_khotab')->where('group_id', $group->id)->update(['channel_id' => $channel]);

            $this->audit->record($actor, 'edit_group', 'khotab', 'group', (int) $group->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
                'channel_cascade' => ['channel_id' => $channel, 'series_rows' => $series, 'item_rows' => $items],
            ], $ip);
        });
    }
}
