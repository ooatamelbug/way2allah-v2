<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «تعديل المادة» — OLD `AnasheedEditAnasheed` → `ConfirmEditAnasheed`
 * (`modules/Anasheed/admin/index.php`, the production working copy on PHP 8.1).
 *
 * OLD's UPDATE: title, description, meta_keywords, meta_description,
 * meta_index, meta_follow, link, trial = 0, group_id, channel_id and
 * author_id (NULL for «غير مصنف»), vedio, fixed, is_published and notes. On a
 * group change: `anasheed - 1` on the old group, `+ 1` on the new one.
 *
 * Owner-approved corrections to OLD (2026-10-23):
 * - **size is persisted.** OLD showed «الحجم بالميجا بايت» prefilled but never
 *   wrote `linksize`. Laravel writes it with Add Item's MB → bytes rule
 *   (`(int) (1024 × 1024 × MB)`). Re-submitting the prefilled, 2-decimal
 *   value unchanged keeps the stored bytes, so a plain save does not round the
 *   size;
 * - **parent_id follows a move.** OLD left the item's stored root stale when
 *   `group_id` changed; Laravel recomputes it from the NEW group's hierarchy
 *   (InsertAnasheedItemAction::rootOf), in the same transaction. Without a
 *   group change `parent_id` is not rewritten. `cat_id` is never touched.
 *
 * Other corrections: authorized (`anasheed.editanasheed`; GreenZone was
 * inert); the group must be one OLD's select offered; one transaction with
 * the row locked; counters never below 0; audited. OLD's `RepairCount()` call
 * is not reproduced: in production it targets tables and a column that do not
 * exist (`nuke_anasheed_authors`, `nuke_anasheed_series`,
 * `nuke_anasheed_groups.count`), so it changed nothing.
 */
final class UpdateAnasheedItemAction
{
    private const TEXT = ['title', 'description', 'meta_keywords', 'meta_description', 'link', 'notes'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** The edit form's prefill: OLD's `sprintf('%01.2f', linksize / 1048576)`. */
    public static function sizeField(int $bytes): string
    {
        return sprintf('%01.2f', $bytes / 1048576);
    }

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException a group OLD's select did not offer
     */
    public function execute(AdminUser $actor, AnasheedItem $item, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'anasheed.editanasheed', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_anasheed')->where('id', $item->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $current = AnasheedItem::query()->findOrFail($item->id);
            $groupId = (int) ($data['group'] ?? 0);
            if (! in_array($groupId, AnasheedItemGroups::allowedIds($current), true)) {
                throw ValidationException::withMessages(['group' => 'القسم المختار غير متاح لهذه المادة']);
            }

            $oldGroupId = (int) $row->group_id;
            $moved = $groupId !== $oldGroupId;

            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'link' => (string) ($data['Anasheed_link'] ?? ''),
                'trial' => 0,
                'group_id' => $groupId,
                'channel_id' => self::nullableId($data['Channel'] ?? null),
                'author_id' => self::nullableId($data['Author'] ?? null),
                'vedio' => (int) ($data['vedio'] ?? 0) === 1 ? 1 : 0,
                'fixed' => (int) ($data['fixed'] ?? 0) === 1 ? 1 : 0,
                'is_published' => (int) ($data['is_published'] ?? 0) === 1 ? 1 : 0,
                'notes' => (string) ($data['Des'] ?? ''),
                'linksize' => self::linksize($data['Anasheedsize'] ?? null, (int) $row->linksize),
            ];
            if ($moved) {
                $after['parent_id'] = InsertAnasheedItemAction::rootOf(AnasheedGroup::query()->findOrFail($groupId));
            }

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_anasheed_anasheed')->where('id', $row->id)->update($after);

            if ($moved) {
                if ($oldGroupId > 0) {
                    $main->table('nuke_anasheed_groups')->where('id', $oldGroupId)->where('anasheed', '>', 0)->decrement('anasheed');
                }
                $main->table('nuke_anasheed_groups')->where('id', $groupId)->increment('anasheed');
            }

            $this->audit->record($actor, 'edit_item', 'anasheed', 'item', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
                'moved' => $moved ? ['from_group' => $oldGroupId, 'to_group' => $groupId, 'parent_id' => $after['parent_id']] : null,
            ], $ip);
        });
    }

    /** An unchanged prefill keeps the stored bytes; anything else is MB → bytes, as Add Item. */
    private static function linksize(mixed $megabytes, int $storedBytes): int
    {
        $submitted = trim((string) ($megabytes ?? ''));
        if ($submitted === self::sizeField($storedBytes)) {
            return $storedBytes;
        }

        return (int) (1024 * 1024 * (float) ($submitted === '' ? 0 : $submitted));
    }

    /** OLD: an empty choice («غير مصنف») is SQL NULL, anything else its integer. */
    private static function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
