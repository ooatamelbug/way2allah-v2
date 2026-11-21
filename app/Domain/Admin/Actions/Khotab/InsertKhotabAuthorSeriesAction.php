<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;

/**
 * «إضافة سلسلة» on an author page — OLD `KhotabInsertSeries` →
 * `ConfirmInsertSeries` (`modules/Khotab/admin/index.php:1894-2032`) with
 * `group_id = 0`: OLD's form locked the group select on «بدون مجموعة» and
 * left the channel select free, so the series is standalone and takes the
 * posted channel (not checked, as in OLD).
 *
 * Writes the same columns as the group page's add-series
 * ({@see InsertKhotabSeriesAction}): title, description, meta_keywords,
 * meta_description, meta_index, meta_follow, author_id, group_id (0), time,
 * vedio, channel_id, des. No counter moves.
 *
 * Corrections: authorized (`khotab.addseries`), one transaction, the id from
 * the insert, audited.
 */
final class InsertKhotabAuthorSeriesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, Author $author, int $vedio, array $data, ?string $ip = null): int
    {
        // The section chosen by `{media}` (OLD AvAdmin($vedio)); the author row carries no section.
        $this->authorizer->authorize($actor, 'khotab.addseries', new KhotabMediaTarget($vedio));

        return DB::connection('main')->transaction(function () use ($actor, $author, $vedio, $data, $ip) {
            $main = DB::connection('main');
            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $author->id)->lockForUpdate()->value('id');
            abort_if($authorId < 1, 404);

            $channel = (int) ($data['Channel'] ?? 0);
            $id = (int) $main->table('nuke_islamic_series')->insertGetId([
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'author_id' => $authorId,
                'group_id' => 0,
                'time' => time(),
                'vedio' => $vedio,
                'channel_id' => $channel,
                'des' => (string) ($data['MoreInfo'] ?? ''),
            ]);

            $this->audit->record($actor, 'add_series', 'khotab', 'series', $id, [
                'group_id' => 0,
                'author_id' => $authorId,
                'vedio' => $vedio,
                'channel_id' => $channel,
                'title' => AuditText::digest((string) ($data['title'] ?? '')),
            ], $ip);

            return $id;
        });
    }
}
