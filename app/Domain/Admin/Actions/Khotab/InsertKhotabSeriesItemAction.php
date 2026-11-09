<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إضافة مادة» from a series page — OLD `KhotabInsertKhotab` →
 * `ConfirmInsertKhotab` (`modules/Khotab/admin/index.php:912-1175`,
 * production working copy).
 *
 * The series panel links with the series' author, id and media type; OLD
 * took the group from the series ({@see KhotabSeriesContext::groupId()}).
 *
 * Channel. OLD rendered the channel select enabled only for a series with no
 * group and no channel; otherwise it was `disabled` and never submitted
 * (stored 0). OLD then redirected to the series page, whose `Series()` ran
 * `UPDATE khotab SET channel_id = series.channel_id WHERE ser_id = X AND
 * channel_id = 0` on every view — so in OLD an item added to a series that
 * has a channel ends with the series' channel. That end result is written
 * here directly; the write-on-every-page-view itself is not reproduced.
 *
 * OLD's checks, in OLD's order: title under 3 or over 250 bytes refused; a
 * link already used by an item of the same author refused, naming it.
 *
 * Then OLD's writes, all reproduced: author `vedio`/`audio` + 1; the item row
 * (same columns as the group page, with `ser_id = X`, `group_id = G`);
 * the series' `count + 1, lastupdate`; the group's `count + 1, lastupdate`
 * when `G > 0`; `weight = id` when 0; with a location, the author, series and
 * group location counts (OLD's order); then the archive.org images.
 *
 * Corrections: authorized (`khotab.additem`; OLD's inert token was
 * `addkhotab`), one transaction with the audit row, the id taken from the
 * insert, and the image downloads restricted and bounded
 * ({@see KhotabArchiveImages}).
 */
final class InsertKhotabSeriesItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
        private readonly KhotabArchiveImages $images,
    ) {}

    /** Whether OLD's add-item form offered a channel choice for this series. */
    public static function channelSelectable(Series $series): bool
    {
        return (int) $series->group_id === 0 && (int) $series->channel_id === 0;
    }

    /**
     * @param  array<string, mixed>  $data  the validated form
     * @return int the new item id
     *
     * @throws ValidationException OLD's refusals, as field errors
     */
    public function execute(AdminUser $actor, Series $series, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.additem', $series);

        $title = (string) ($data['title'] ?? '');
        if (strlen(trim($title)) < 3) {
            throw ValidationException::withMessages(['title' => 'عنوان المادة قصير جدا .... تأكد من كتابة عنوان المادة كاملا']);
        }
        if (strlen($title) > 250) {
            throw ValidationException::withMessages(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
        }

        $link = trim((string) ($data['link'] ?? ''));

        $id = DB::connection('main')->transaction(function () use ($actor, $series, $data, $title, $link, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_series')->where('id', $series->id)->lockForUpdate()->first(['id', 'author_id', 'group_id', 'channel_id', 'vedio']);
            abort_if($row === null, 404);

            $existing = $main->table('nuke_islamic_khotab')->where('link', $link)->where('author', $row->author_id)->first(['id', 'title']);
            if ($existing !== null) {
                throw ValidationException::withMessages(['link' => 'هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : #'.$existing->id.' '.$existing->title]);
            }

            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $row->author_id)->lockForUpdate()->value('id');
            if ($authorId < 1) {
                throw ValidationException::withMessages(['title' => 'الداعية غير موجود']);
            }

            $groupId = KhotabSeriesContext::groupId((int) $row->group_id);
            $vedio = (int) $row->vedio;
            $channel = match (true) {
                (int) $row->channel_id > 0 => (int) $row->channel_id,
                (int) $row->group_id === 0 => max(0, (int) ($data['Channel'] ?? 0)),
                default => 0,
            };
            $location = (int) ($data['Location'] ?? 0);
            $size = (int) ($data['original_size'] ?? 0) > 0
                ? (int) $data['original_size']
                : (int) (1024 * 1024 * (float) ($data['size'] ?? 0));
            $now = time();

            $main->table('nuke_islamic_authors')->where('id', $authorId)
                ->update([$vedio === 1 ? 'vedio' : 'audio' => DB::raw(($vedio === 1 ? 'vedio' : 'audio').' + 1')]);

            $id = (int) $main->table('nuke_islamic_khotab')->insertGetId([
                'title' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'author' => $authorId,
                'link' => $link,
                'linksize' => $size,
                'time' => $now,
                'ser_id' => (int) $row->id,
                'group_id' => $groupId,
                'vedio' => $vedio,
                'notes' => (string) ($data['MoreInfo'] ?? ''),
                'down' => 2,
                'newslist' => (int) ($data['newslist'] ?? 0) === 1 ? 1 : 0,
                'fixed' => in_array($data['fixed'] ?? null, [1, '1', 'on'], true) ? 1 : 0,
                'channel_id' => $channel,
                'location_id' => $location,
                'lastmirror' => $now,
            ]);

            $main->table('nuke_islamic_series')->where('id', $row->id)
                ->update(['count' => DB::raw('count + 1'), 'lastupdate' => $now]);
            if ($groupId > 0) {
                $main->table('nuke_islamic_groups')->where('id', $groupId)
                    ->update(['count' => DB::raw('count + 1'), 'lastupdate' => $now]);
            }
            $main->table('nuke_islamic_khotab')->where('id', $id)->where('weight', 0)->update(['weight' => $id]);

            if ($location > 0) {
                $this->locations->update('author', $authorId, $location);
                $this->locations->update('series', (int) $row->id, $location);
                $this->locations->update('group', $groupId, $location);
            }

            $this->audit->record($actor, 'add_item', 'khotab', 'item', $id, [
                'series_id' => (int) $row->id,
                'group_id' => $groupId,
                'author_id' => $authorId,
                'vedio' => $vedio,
                'channel_id' => $channel,
                'link' => $link,
                'linksize' => $size,
                'location_id' => $location,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });

        // OLD downloaded the images after its database writes, outside any
        // transaction; so do we, so a slow archive.org cannot hold row locks.
        $flags = $this->images->store($id, (string) ($data['archive_gif'] ?? ''), (string) ($data['archive_frame'] ?? ''));
        if ($flags !== []) {
            DB::connection('main')->table('nuke_islamic_khotab')->where('id', $id)->update($flags);
        }

        return $id;
    }
}
