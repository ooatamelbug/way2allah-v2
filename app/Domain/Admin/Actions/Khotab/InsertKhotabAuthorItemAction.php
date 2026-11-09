<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إضافة مادة» on an author page — OLD `KhotabInsertKhotab` →
 * `ConfirmInsertKhotab` (`modules/Khotab/admin/index.php:912-1175`) with no
 * series and no group: a standalone item, whose channel select OLD left free
 * (the posted channel is stored, unchecked, as in OLD).
 *
 * The same flow as the approved group / series add-item
 * ({@see InsertKhotabSeriesItemAction}): OLD's refusals in OLD's order (title
 * under 3 or over 250 bytes; a link already used by an item of the same
 * author); author `vedio`/`audio` + 1; the item row with `ser_id = 0`,
 * `group_id = 0`; `weight = id` when 0; with a location, the author's
 * location count only (OLD skipped series / group when their ids are 0);
 * then the archive.org images ({@see KhotabArchiveImages}). No series or
 * group counter moves.
 *
 * Corrections: authorized (`khotab.additem`; OLD's inert token was
 * `addkhotab`), one transaction with the audit row, the id from the insert,
 * the image downloads restricted to archive.org and bounded.
 */
final class InsertKhotabAuthorItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
        private readonly KhotabArchiveImages $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form
     * @return int the new item id
     *
     * @throws ValidationException OLD's refusals, as field errors
     */
    public function execute(AdminUser $actor, Author $author, int $vedio, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.additem', $author);

        $title = (string) ($data['title'] ?? '');
        if (strlen(trim($title)) < 3) {
            throw ValidationException::withMessages(['title' => 'عنوان المادة قصير جدا .... تأكد من كتابة عنوان المادة كاملا']);
        }
        if (strlen($title) > 250) {
            throw ValidationException::withMessages(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
        }

        $link = trim((string) ($data['link'] ?? ''));

        $id = DB::connection('main')->transaction(function () use ($actor, $author, $vedio, $data, $title, $link, $ip) {
            $main = DB::connection('main');

            $existing = $main->table('nuke_islamic_khotab')->where('link', $link)->where('author', $author->id)->first(['id', 'title']);
            if ($existing !== null) {
                throw ValidationException::withMessages(['link' => 'هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : #'.$existing->id.' '.$existing->title]);
            }

            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $author->id)->lockForUpdate()->value('id');
            if ($authorId < 1) {
                throw ValidationException::withMessages(['title' => 'الداعية غير موجود']);
            }

            $channel = max(0, (int) ($data['Channel'] ?? 0));
            $location = (int) ($data['Location'] ?? 0);
            $size = (int) ($data['original_size'] ?? 0) > 0
                ? (int) $data['original_size']
                : (int) (1024 * 1024 * (float) ($data['size'] ?? 0));
            $now = time();
            $type = $vedio === 1 ? 'vedio' : 'audio';

            $main->table('nuke_islamic_authors')->where('id', $authorId)->update([$type => DB::raw($type.' + 1')]);

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
                'ser_id' => 0,
                'group_id' => 0,
                'vedio' => $vedio,
                'notes' => (string) ($data['MoreInfo'] ?? ''),
                'down' => 2,
                'newslist' => (int) ($data['newslist'] ?? 0) === 1 ? 1 : 0,
                'fixed' => in_array($data['fixed'] ?? null, [1, '1', 'on'], true) ? 1 : 0,
                'channel_id' => $channel,
                'location_id' => $location,
                'lastmirror' => $now,
            ]);

            $main->table('nuke_islamic_khotab')->where('id', $id)->where('weight', 0)->update(['weight' => $id]);

            if ($location > 0) {
                $this->locations->update('author', $authorId, $location);
            }

            $this->audit->record($actor, 'add_item', 'khotab', 'item', $id, [
                'series_id' => 0,
                'group_id' => 0,
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
