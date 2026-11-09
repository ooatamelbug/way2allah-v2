<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OLD Admin Migration Phase 6 — add a CD release. Replaces OLD
 * `InsertW2ACD` + `cdsConfirmInsert` (`modules/w2acd/admin/index.php:291-406`):
 *
 * - one `nuke_w2acd_w2acd` row: `title`, `mytime = time()`, `group_id` (an
 *   existing group, else 0), `parent_id` = that group, `link` / `cd` (the
 *   download links, CdLinkList) and `thumbnail` (the uploaded images,
 *   CdImageList);
 * - the group's `w2acd` counter +1 (none for the main list, group 0).
 *
 * `order_in_group` is written as 1 — the real column default OLD relied on
 * (`information_schema`) — and `lastvisit` / `downloader`, NOT NULL with no
 * default, as 0 / '' (what OLD's INSERT got from non-strict MySQL).
 *
 * Images are written before the transaction; if the transaction fails they
 * are removed again, so a failed create leaves neither a row nor files.
 */
final class CreateCdReleaseAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  list<array{title: string, url: string}>  $links
     * @param  list<UploadedFile>  $images
     */
    public function execute(AdminUser $actor, string $title, int $groupId, array $links, array $images, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds');

        $stored = [];
        try {
            foreach ($images as $slot => $file) {
                $stored[] = CdImageList::store($file, $slot);
            }

            return DB::connection('main')->transaction(function () use ($actor, $title, $groupId, $links, $stored, $ip) {
                $groupId = CdGroupCounter::resolve($groupId);
                $list = CdLinkList::format($links);

                $id = (int) DB::connection('main')->table('nuke_w2acd_w2acd')->insertGetId([
                    'title' => $title,
                    'mytime' => time(),
                    'group_id' => $groupId,
                    'parent_id' => $groupId,
                    'link' => $list['link'],
                    'cd' => $list['cd'],
                    'thumbnail' => CdImageList::format($stored),
                    'order_in_group' => 1,
                    'lastvisit' => 0,
                    'downloader' => '',
                ]);

                $counter = CdGroupCounter::move($groupId, +1);

                $this->audit->record($actor, 'add_cd', 'w2acd', 'cd_release', $id, [
                    'title' => CdAuditText::of($title),
                    'group_id' => $groupId,
                    'links' => CdAuditText::links($links),
                    'images' => $stored,
                    'group_counter' => $counter,
                ], $ip);

                return $id;
            });
        } catch (Throwable $e) {
            CdImageList::discard($stored);
            throw $e;
        }
    }
}
