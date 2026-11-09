<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedGroup;
use Illuminate\Support\Facades\DB;

/**
 * «إضافة مادة» — OLD `AnasheedInsertAnasheed` → `ConfirmInsertAnasheed`
 * (`modules/Anasheed/admin/index.php`). Production runs the old-project
 * WORKING COPY on PHP 8.1 (owner-verified SHA-256, 2026-10-23), not HEAD.
 *
 * OLD's insert: title, description, meta_keywords, meta_description,
 * meta_index, meta_follow, link, linksize (the MB field × 1024 × 1024),
 * mytime (now), group_id, down = 2, parent_id (the group's ROOT, found by
 * walking up), channel_id and author_id (NULL for «غير مصنف»), vedio, fixed,
 * is_published and notes; then `weight = id`, then `anasheed + 1` on the
 * group.
 *
 * `is_published` is the form's «نشر علي المنصة» checkbox (checked by
 * default): 1 when checked, 0 when not. The production column is
 * `NOT NULL DEFAULT 0`, so it is always written — leaving it out (as the
 * first version of this action did, built from HEAD) created unpublished
 * items. Corrected on production evidence, 2026-10-23.
 * OLD's `weight = id WHERE weight = 0` ran table-wide; here it targets the new
 * row only (the local copy has no other `weight = 0` row, so the effect is
 * the same).
 *
 * Corrections: authorized (`anasheed.addanasheed`; OLD's GreenZone was
 * inert); the insert, the weight, the counter and the audit row in one
 * transaction, so the counter moves only when the item exists (OLD: separate
 * statements — a failed insert still bumped the counter).
 */
final class InsertAnasheedItemAction
{
    /** OLD InsertAnasheed(): no author list under these two roots (hidden `Author` = 0). */
    public const ROOTS_WITHOUT_AUTHOR = [57, 12];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** The group's root id — OLD's `while ($Group[parent_id] > 0)` walk (bounded against a cycle). */
    public static function rootOf(AnasheedGroup $group): int
    {
        $main = DB::connection('main');
        $id = (int) $group->id;
        $parentId = (int) $group->parent_id;

        for ($steps = 0; $parentId > 0 && $steps < 50; $steps++) {
            $row = $main->table('nuke_anasheed_groups')->where('id', $parentId)->first(['id', 'parent_id']);
            if ($row === null) {
                break;
            }
            $id = (int) $row->id;
            $parentId = (int) $row->parent_id;
        }

        return $id;
    }

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, AnasheedGroup $group, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'anasheed.addanasheed', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $groupId = (int) $main->table('nuke_anasheed_groups')->where('id', $group->id)->lockForUpdate()->value('id');
            abort_if($groupId < 1, 404);

            $root = self::rootOf($group);
            $author = in_array($root, self::ROOTS_WITHOUT_AUTHOR, true) ? 0 : self::nullableId($data['Author'] ?? null);
            $title = (string) ($data['title'] ?? '');

            $id = (int) $main->table('nuke_anasheed_anasheed')->insertGetId([
                'title' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'link' => (string) ($data['Anasheedfile'] ?? ''),
                'linksize' => (int) (1024 * 1024 * (float) ($data['Anasheedsize'] ?? 0)),
                'mytime' => time(),
                'group_id' => $groupId,
                'down' => 2,
                'parent_id' => $root,
                'channel_id' => self::nullableId($data['Channel'] ?? null),
                'author_id' => $author,
                'vedio' => (int) ($data['vedio'] ?? 1) === 0 ? 0 : 1,
                'fixed' => (int) ($data['fixed'] ?? 0) === 1 ? 1 : 0,
                'is_published' => empty($data['is_published']) ? 0 : 1,
                'notes' => (string) ($data['MoreInfo'] ?? ''),
            ]);

            $main->table('nuke_anasheed_anasheed')->where('id', $id)->where('weight', 0)->update(['weight' => $id]);
            $main->table('nuke_anasheed_groups')->where('id', $groupId)->increment('anasheed');

            $this->audit->record($actor, 'add_item', 'anasheed', 'item', $id, [
                'group_id' => $groupId,
                'root_id' => $root,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }

    /** OLD: an empty choice («غير مصنف») is SQL NULL, anything else its integer. */
    private static function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
