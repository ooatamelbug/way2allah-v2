<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * OLD Admin Migration Phase 6 — edit a CD release. Replaces OLD
 * `EditW2ACD` + `cdsConfirmEdit` (`:415-563`) and `del_cd_img` (`:592-614`):
 * the title, the download links, the group and the image list.
 *
 * Images, as OLD: each existing image is kept, replaced in place (its list
 * position — the first is the cover — is preserved) or dropped from the
 * list; new images are appended; at most five. Dropped or replaced files
 * are not deleted from disk (OLD never deleted them).
 *
 * Corrected: OLD changed `group_id` without moving the group counters;
 * here the old group −1 and the new group +1 (floored). `parent_id` is left
 * as OLD left it on edit.
 *
 * Only changed columns are written; an unchanged submission writes nothing
 * and records no audit row.
 */
final class UpdateCdReleaseAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  list<array{title: string, url: string}>  $links
     * @param  list<array{name: string, keep: bool, file: UploadedFile|null}>  $existing  in current list order
     * @param  list<UploadedFile>  $added
     * @return bool whether anything changed
     */
    public function execute(AdminUser $actor, W2acdItem $release, string $title, int $groupId, array $links, array $existing, array $added, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $release);

        $stored = [];
        try {
            return DB::connection('main')->transaction(function () use ($actor, $release, $title, $groupId, $links, $existing, $added, $ip, &$stored) {
                $main = DB::connection('main');
                $row = $main->table('nuke_w2acd_w2acd')->where('id', $release->getKey())->lockForUpdate()
                    ->first(['id', 'title', 'group_id', 'link', 'cd', 'thumbnail']);
                abort_if($row === null, 404);

                // The plan is applied to the images as they are now, under lock.
                $current = CdImageList::parse($row->thumbnail);
                $plan = [];
                foreach ($existing as $entry) {
                    $plan[$entry['name']] = $entry;
                }
                $images = [];
                foreach ($current as $position => $name) {
                    $entry = $plan[$name] ?? ['keep' => true, 'file' => null];
                    if ($entry['file'] instanceof UploadedFile) {
                        $images[] = $stored[] = CdImageList::store($entry['file'], $position);
                    } elseif ($entry['keep']) {
                        $images[] = $name;
                    }
                }
                foreach ($added as $file) {
                    $images[] = $stored[] = CdImageList::store($file, count($images));
                }
                if (count($images) > CdImageList::MAX_IMAGES) {
                    throw new InvalidArgumentException('A release has at most '.CdImageList::MAX_IMAGES.' images.');
                }

                $groupId = CdGroupCounter::resolve($groupId);
                $list = CdLinkList::format($links);
                $after = ['title' => $title, 'group_id' => $groupId, 'link' => $list['link'], 'cd' => $list['cd'], 'thumbnail' => CdImageList::format($images)];
                $before = ['title' => (string) $row->title, 'group_id' => (int) $row->group_id, 'link' => (string) $row->link, 'cd' => (string) $row->cd, 'thumbnail' => (string) $row->thumbnail];
                $changes = array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH);

                if ($changes === []) {
                    return false;
                }

                $main->table('nuke_w2acd_w2acd')->where('id', $row->id)->update($changes);

                $counters = [];
                if (isset($changes['group_id'])) {
                    $counters = array_filter([
                        'from' => CdGroupCounter::move($before['group_id'], -1),
                        'to' => CdGroupCounter::move($groupId, +1),
                    ]);
                }

                $describe = fn (array $values) => array_filter([
                    'title' => isset($changes['title']) ? CdAuditText::of($values['title']) : null,
                    'group_id' => isset($changes['group_id']) ? $values['group_id'] : null,
                    'links' => isset($changes['link']) || isset($changes['cd']) ? CdAuditText::links(CdLinkList::parse($values['link'], $values['cd'])) : null,
                    'images' => isset($changes['thumbnail']) ? CdImageList::parse($values['thumbnail']) : null,
                ], fn ($v) => $v !== null);

                $this->audit->record($actor, 'edit_cd', 'w2acd', 'cd_release', (int) $row->id, array_filter([
                    'before' => $describe($before),
                    'after' => $describe($after),
                    'group_counters' => $counters,
                ]), $ip);

                return true;
            });
        } catch (Throwable $e) {
            CdImageList::discard($stored);
            throw $e;
        }
    }
}
