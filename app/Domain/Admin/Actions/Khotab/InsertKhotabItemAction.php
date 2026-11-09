<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * «إضافة مادة» from a group page — OLD `KhotabInsertKhotab` →
 * `ConfirmInsertKhotab` (`modules/Khotab/admin/index.php:911-1170`,
 * production working copy — the owner verified production = this file).
 *
 * From the group page the form fixes author, group (no series) and media
 * type. Its channel select is rendered `disabled` with the single option
 * «بدون قناة» and — unlike the series form — never re-enabled, so OLD always
 * stored `channel_id = 0` for an item added here, even in a group that has a
 * channel. Reproduced as OLD behaves.
 *
 * OLD's checks, in OLD's order: title shorter than 3 bytes, or longer than
 * 250 bytes, is refused (OLD measured with `strlen`, i.e. bytes); a link
 * already used by an item of the same author is refused, naming that item.
 *
 * Then OLD's writes, all reproduced:
 *
 * 1. author counter `vedio` or `audio` + 1;
 * 2. the `nuke_islamic_khotab` row — title, description, meta_keywords,
 *    meta_description, meta_index, meta_follow, author, link, linksize,
 *    time, ser_id (0), group_id, vedio, notes, down = 2, newslist, fixed,
 *    channel_id, location_id, lastmirror = time;
 *    `linksize` = `original_size` when archive.org supplied it, else
 *    `1024 * 1024 * size` (MB typed by hand);
 * 3. the group's `count + 1, lastupdate = time`;
 * 4. `weight = id` when the new row's weight is 0;
 * 5. with a location: update_author_location, then update_group_location;
 * 6. the archive.org animated GIF → `media/khotab_gifs/{id/1000}/{id}.gif`
 *    and `gif = 1`; the still frame → `media/khotab_frames/{id/1000}/{id}.jpg`
 *    and `frame = 1`.
 *
 * Corrections: authorized (`khotab.additem`; OLD's inert token was
 * `addkhotab`), the database writes and the audit row are one transaction,
 * the new id comes from the insert (OLD re-selected by link and time), and
 * the image downloads are bounded by a timeout and limited to archive.org
 * URLs — OLD fetched any URL posted in the hidden fields. A failed image
 * download leaves `gif`/`frame` at 0 (OLD would `die()` half-way).
 */
final class InsertKhotabItemAction
{
    private const ARCHIVE_HOST = '/(^|\.)archive\.org$/i';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form
     * @return int the new item id
     *
     * @throws ValidationException OLD's refusals, as field errors
     */
    public function execute(AdminUser $actor, KhotabGroup $group, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.additem', $group);

        $title = (string) ($data['title'] ?? '');
        if (strlen(trim($title)) < 3) {
            throw ValidationException::withMessages(['title' => 'عنوان المادة قصير جدا .... تأكد من كتابة عنوان المادة كاملا']);
        }
        if (strlen($title) > 250) {
            throw ValidationException::withMessages(['title' => 'عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات']);
        }

        $link = trim((string) ($data['link'] ?? ''));

        $id = DB::connection('main')->transaction(function () use ($actor, $group, $data, $title, $link, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'author_id', 'vedio']);
            abort_if($row === null, 404);

            $existing = $main->table('nuke_islamic_khotab')->where('link', $link)->where('author', $row->author_id)->first(['id', 'title']);
            if ($existing !== null) {
                throw ValidationException::withMessages(['link' => 'هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : #'.$existing->id.' '.$existing->title]);
            }

            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $row->author_id)->lockForUpdate()->value('id');
            if ($authorId < 1) {
                throw ValidationException::withMessages(['title' => 'الداعية غير موجود']);
            }

            $vedio = (int) $row->vedio;
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
                'ser_id' => 0,
                'group_id' => (int) $row->id,
                'vedio' => $vedio,
                'notes' => (string) ($data['MoreInfo'] ?? ''),
                'down' => 2,
                'newslist' => (int) ($data['newslist'] ?? 0) === 1 ? 1 : 0,
                'fixed' => in_array($data['fixed'] ?? null, [1, '1', 'on'], true) ? 1 : 0,
                'channel_id' => 0,
                'location_id' => $location,
                'lastmirror' => $now,
            ]);

            $main->table('nuke_islamic_groups')->where('id', $row->id)
                ->update(['count' => DB::raw('count + 1'), 'lastupdate' => $now]);
            $main->table('nuke_islamic_khotab')->where('id', $id)->where('weight', 0)->update(['weight' => $id]);

            if ($location > 0) {
                $this->locations->update('author', $authorId, $location);
                $this->locations->update('group', (int) $row->id, $location);
            }

            $this->audit->record($actor, 'add_item', 'khotab', 'item', $id, [
                'group_id' => (int) $row->id,
                'author_id' => $authorId,
                'vedio' => $vedio,
                'link' => $link,
                'linksize' => $size,
                'location_id' => $location,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });

        // OLD downloaded the images after its database writes, outside any
        // transaction; so do we, so a slow archive.org cannot hold row locks.
        $flags = [];
        if ($this->download((string) ($data['archive_gif'] ?? ''), MediaPathResolver::path('khotab_gifs', $id, 'gif'))) {
            $flags['gif'] = 1;
        }
        if ($this->download((string) ($data['archive_frame'] ?? ''), MediaPathResolver::path('khotab_frames', $id, 'jpg'))) {
            $flags['frame'] = 1;
        }
        if ($flags !== []) {
            DB::connection('main')->table('nuke_islamic_khotab')->where('id', $id)->update($flags);
        }

        return $id;
    }

    /** @param string $relative `media/…` as MediaPathResolver builds it */
    private function download(string $url, string $relative): bool
    {
        if ($url === '' || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            || preg_match(self::ARCHIVE_HOST, (string) parse_url($url, PHP_URL_HOST)) !== 1) {
            return false;
        }

        try {
            $response = Http::timeout(max(1, (int) config('admin_khotab.archive_timeout', 20)))->get($url);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful() || $response->body() === '') {
            return false;
        }

        $path = rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr($relative, strlen('media/'));
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $response->body()) !== false;
    }
}
