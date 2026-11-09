<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Models\Mobile\MobileSubmission;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — Mobile materials and member submissions.
 * Replaces OLD `DownloadsAddDownload` (direct add, and approval of a
 * submission with `new = 1`), `DownloadsDelNew`, `DownloadsModDownload(S)`
 * and `DownloadsDelDownload`:
 *
 * - add / approve: refused when a published material already uses the URL
 *   (OLD `_DOWNLOADALREADYEXT`); otherwise a material dated now, counters 0,
 *   `submitter` kept from the submission; approving removes the submission;
 * - reject: the submission is removed;
 * - edit: every field, including the category and the hit count, as OLD's
 *   edit form; unchanged submissions write nothing;
 * - delete: the material row only (its votes, editorial and requests stay,
 *   as in OLD).
 */
final class MobileMaterialAdmin extends MobileAdminAction
{
    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} $data */
    public function create(AdminUser $actor, array $data, ?string $ip = null): int
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $data, $ip) {
            $lid = $this->insert($data, '', 0);
            $this->record($actor, 'add_mobile', 'mobile_material', $lid, self::describe($data), $ip);

            return $lid;
        });
    }

    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} $data */
    public function approve(AdminUser $actor, MobileSubmission $submission, array $data, ?string $ip = null): int
    {
        $this->authorize($actor, $submission);

        return DB::connection('main')->transaction(function () use ($actor, $submission, $data, $ip) {
            $main = DB::connection('main');
            $pending = $main->table('nuke_downloads_newdownload')->where('lid', $submission->getKey())->lockForUpdate()->first();
            abort_if($pending === null, 404);

            $lid = $this->insert($data, (string) $pending->submitter, 0);
            $main->table('nuke_downloads_newdownload')->where('lid', $pending->lid)->delete();

            $this->record($actor, 'approve_mobile_submission', 'mobile_material', $lid, [
                'submission_id' => (int) $pending->lid,
                'submitter' => self::text($pending->submitter),
            ] + self::describe($data), $ip);

            return $lid;
        });
    }

    public function reject(AdminUser $actor, MobileSubmission $submission, ?string $ip = null): void
    {
        $this->authorize($actor, $submission);

        DB::connection('main')->transaction(function () use ($actor, $submission, $ip) {
            $main = DB::connection('main');
            $pending = $main->table('nuke_downloads_newdownload')->where('lid', $submission->getKey())->lockForUpdate()->first();
            abort_if($pending === null, 404);

            $main->table('nuke_downloads_newdownload')->where('lid', $pending->lid)->delete();
            $this->record($actor, 'reject_mobile_submission', 'mobile_submission', (int) $pending->lid, [
                'submitter' => self::text($pending->submitter),
                'title' => self::text($pending->title),
                'url' => self::text($pending->url),
            ], $ip);
        });
    }

    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string, hits: int} $data */
    public function update(AdminUser $actor, MobileMaterial $material, array $data, ?string $ip = null): bool
    {
        $this->authorize($actor, $material);

        return DB::connection('main')->transaction(function () use ($actor, $material, $data, $ip) {
            $main = DB::connection('main');
            $row = (array) ($main->table('nuke_downloads_downloads')->where('lid', $material->getKey())->lockForUpdate()->first() ?? abort(404));

            $changes = [];
            foreach ($data as $key => $value) {
                if ((string) $row[$key] !== (string) $value) {
                    $changes[$key] = $value;
                }
            }
            if ($changes === []) {
                return false;
            }

            $main->table('nuke_downloads_downloads')->where('lid', $row['lid'])->update($changes);
            $this->record($actor, 'edit_mobile', 'mobile_material', (int) $row['lid'], [
                'before' => self::describe(array_intersect_key($row, $changes)),
                'after' => self::describe($changes),
            ], $ip);

            return true;
        });
    }

    public function delete(AdminUser $actor, MobileMaterial $material, ?string $ip = null): void
    {
        $this->authorize($actor, $material);

        DB::connection('main')->transaction(function () use ($actor, $material, $ip) {
            $main = DB::connection('main');
            $row = (array) ($main->table('nuke_downloads_downloads')->where('lid', $material->getKey())->lockForUpdate()->first() ?? abort(404));

            $main->table('nuke_downloads_downloads')->where('lid', $row['lid'])->delete();
            $this->record($actor, 'del_mobile', 'mobile_material', (int) $row['lid'], [
                'deleted' => self::describe(array_intersect_key($row, array_flip(['cid', 'title', 'url', 'description', 'hits', 'submitter']))) + [
                    'totalvotes' => (int) $row['totalvotes'], 'date' => (string) $row['date'],
                ],
            ], $ip);
        });
    }

    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} $data */
    private function insert(array $data, string $submitter, int $hits): int
    {
        $main = DB::connection('main');
        if ($main->table('nuke_downloads_downloads')->where('url', $data['url'])->lockForUpdate()->exists()) {
            throw new MobileRefused('خطأ: تم ادخال هذا الرابط في قاعدة البيانات!');
        }

        return (int) $main->table('nuke_downloads_downloads')->insertGetId($data + [
            'sid' => 0, 'date' => date('Y-m-d H:i:s'), 'hits' => $hits, 'submitter' => $submitter,
            'downloadratingsummary' => 0, 'totalvotes' => 0, 'totalcomments' => 0,
        ], 'lid');
    }
}
