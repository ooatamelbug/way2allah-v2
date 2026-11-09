<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «تعديل السلسلة» — OLD `KhotabEditSeries` → `ConfirmEditSeries`
 * (`modules/Khotab/admin/index.php:2034-2208`, production working copy).
 *
 * OLD's three branches, reproduced exactly:
 *
 * 1. The posted `group` differs from the series' `group_id`:
 *    - it names a group of the same author and media type → the series
 *      takes that `group_id` and that group's `channel_id`, and every item
 *      `WHERE ser_id = X` gets the same `group_id` and `channel_id`;
 *    - otherwise (including «بدون مجموعة», 0) → the series and its items
 *      get `group_id = 0`; channels are left as they are.
 * 2. Unchanged, and the series is in a group → only the series' own fields;
 *    the channel is not editable (OLD rendered it as plain text).
 * 3. Unchanged, and the series has no group → the posted channel (refused,
 *    with OLD's message, when it does not exist) is written to the series
 *    and to every item `WHERE ser_id = X`.
 *
 * Each branch also writes title, description, meta_keywords,
 * meta_description, meta_index, meta_follow, hidden (`C1`), ramadan and des.
 *
 * Corrections: authorized (`khotab.editseries`; OLD's
 * `GreenZone('editseries')` was inert), one transaction, audited with the
 * free-text values digested. OLD's audio/video moderator split (`AvAdmin`)
 * is not reproduced (owner decision, 2026-10-12).
 */
final class UpdateKhotabSeriesAction
{
    private const TEXT = ['title', 'description', 'meta_keywords', 'meta_description', 'des'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     * @return int the posted group id — where OLD sent the admin next (> 0: that group page)
     *
     * @throws ValidationException OLD's unknown-channel refusal
     */
    public function execute(AdminUser $actor, Series $series, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.editseries', $series);

        $postedGroup = (int) ($data['group'] ?? 0);

        DB::connection('main')->transaction(function () use ($actor, $series, $data, $postedGroup, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'hidden' => empty($data['C1']) ? 0 : 1,
                'ramadan' => empty($data['ramadan']) ? 0 : 1,
                'des' => (string) ($data['Des'] ?? ''),
            ];
            $items = [];

            if ($postedGroup !== (int) $row->group_id) {
                $group = $main->table('nuke_islamic_groups')
                    ->where('id', $postedGroup)->where('author_id', $row->author_id)->where('vedio', $row->vedio)
                    ->first(['id', 'channel_id']);

                if ($group !== null && (int) $group->id > 0) {
                    $after['group_id'] = (int) $group->id;
                    $after['channel_id'] = (int) $group->channel_id;
                    $items = ['channel_id' => (int) $group->channel_id, 'group_id' => (int) $group->id];
                    $branch = 'moved_to_group';
                } else {
                    $after['group_id'] = 0;
                    $items = ['group_id' => 0];
                    $branch = 'removed_from_group';
                }
            } elseif ((int) $row->group_id > 0) {
                $branch = 'grouped';
            } else {
                $channel = (int) ($data['Channel'] ?? 0);
                if ($channel > 0 && ! $main->table('nuke_sat_channels')->where('id', $channel)->exists()) {
                    throw ValidationException::withMessages(['Channel' => 'القناة  غير موجودة بقواعد البيانات']);
                }
                $after['channel_id'] = $channel;
                $items = ['channel_id' => $channel];
                $branch = 'ungrouped';
            }

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_islamic_series')->where('id', $row->id)->update($after);
            $itemRows = $items === [] ? 0 : $main->table('nuke_islamic_khotab')->where('ser_id', $row->id)->update($items);

            $this->audit->record($actor, 'edit_series', 'khotab', 'series', (int) $row->id, [
                'branch' => $branch,
                'changes' => AuditText::changes($before, $after, self::TEXT),
                'item_cascade' => ['set' => $items, 'item_rows' => $itemRows],
            ], $ip);
        });

        return $postedGroup;
    }
}
