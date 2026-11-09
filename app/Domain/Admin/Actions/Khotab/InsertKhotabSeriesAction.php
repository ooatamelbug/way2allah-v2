<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use Illuminate\Support\Facades\DB;

/**
 * «إضافة سلسلة» from a group page — OLD `KhotabInsertSeries` →
 * `ConfirmInsertSeries` (`modules/Khotab/admin/index.php:1894-2032`,
 * production working copy).
 *
 * From the group page OLD's form fixes the author, the group, the media type
 * and the channel: the group and channel selects are rendered `disabled` and
 * re-enabled on submit, so they always carry the group's own values. This
 * action therefore takes them from the group, never from the request.
 *
 * Writes OLD's column set on a new `nuke_islamic_series` row: title,
 * description, meta_keywords, meta_description, meta_index, meta_follow,
 * author_id, group_id, time, vedio, channel_id, des. No counter: OLD moved
 * none (a new series has no items, and the group's `count` counts items).
 *
 * Corrections: authorized (`khotab.addseries`), one transaction, audited;
 * the new id comes from the insert itself, not from OLD's re-select by
 * title (which could pick an older series with the same title).
 */
final class InsertKhotabSeriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{title?: ?string, description?: ?string, meta_keywords?: ?string, meta_description?: ?string, meta_index?: mixed, meta_follow?: mixed, MoreInfo?: ?string}  $data
     */
    public function execute(AdminUser $actor, KhotabGroup $group, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addseries', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'author_id', 'vedio', 'channel_id']);
            abort_if($row === null, 404);

            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $row->author_id)->value('id');
            abort_if($authorId < 1, 422, 'الداعية غير موجود');

            $id = (int) $main->table('nuke_islamic_series')->insertGetId([
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'author_id' => $authorId,
                'group_id' => (int) $row->id,
                'time' => time(),
                'vedio' => (int) $row->vedio,
                'channel_id' => (int) $row->channel_id,
                'des' => (string) ($data['MoreInfo'] ?? ''),
            ]);

            $this->audit->record($actor, 'add_series', 'khotab', 'series', $id, [
                'group_id' => (int) $row->id,
                'author_id' => $authorId,
                'vedio' => (int) $row->vedio,
                'channel_id' => (int) $row->channel_id,
                'title' => AuditText::digest((string) ($data['title'] ?? '')),
            ], $ip);

            return $id;
        });
    }
}
