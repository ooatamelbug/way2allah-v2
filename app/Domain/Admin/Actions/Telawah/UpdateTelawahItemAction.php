<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «تعديل» on the item page — OLD `TelawahEditTelawah` → `ConfirmEditTelawah`
 * (`modules/Telawah/admin/index.php`:257-399, production-hash-verified).
 *
 * OLD's UPDATE: title, link, linksize (MB × 1024 × 1024), vedio, sorah,
 * group_id and notes. On a group change: `telawah - 1` on the old group
 * (only while > 0) and `+ 1` on the new one.
 *
 * Owner-approved corrections to OLD (2026-10-29):
 * - **parent_id follows a group change.** OLD left the stored root stale;
 *   Laravel recomputes it from the NEW group's hierarchy
 *   (TelawahItemGroups::rootOf) in the same transaction. Without a group
 *   change `parent_id` is not rewritten;
 * - **an unchanged size is not rounded.** OLD prefilled the size as a
 *   2-decimal MB value and wrote it back, so a plain save silently rounded
 *   the stored bytes. Re-submitting the prefill unchanged keeps the stored
 *   bytes; any other value is MB → bytes, as OLD.
 *
 * OLD's `RepairCount()` ran only when `author > 0`; the table has no
 * `author` column, so it never ran and is not reproduced.
 *
 * Other corrections: authorized (`telawah.edittelawah`; GreenZone was
 * inert); the group must be one OLD's select offered; one transaction with
 * the row locked; audited.
 */
final class UpdateTelawahItemAction
{
    private const TEXT = ['title', 'link', 'notes'];

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
    public function execute(AdminUser $actor, TelawahItem $item, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'telawah.edittelawah', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_telawah_telawah')->where('id', $item->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $current = TelawahItem::query()->findOrFail($item->id);
            $groupId = (int) ($data['group'] ?? 0);
            if (! in_array($groupId, TelawahItemGroups::editAllowedIds($current), true)) {
                throw ValidationException::withMessages(['group' => 'القسم المختار غير متاح لهذه التلاوة']);
            }

            $oldGroupId = (int) $row->group_id;
            $moved = $groupId !== $oldGroupId;

            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'link' => (string) ($data['Telawah_link'] ?? ''),
                'linksize' => self::linksize($data['Telawahsize'] ?? null, (int) $row->linksize),
                'vedio' => (int) ($data['vedio'] ?? 0) === 1 ? 1 : 0,
                'sorah' => (int) ($data['sorah'] ?? 0),
                'group_id' => $groupId,
                'notes' => (string) ($data['Des'] ?? ''),
            ];
            if ($moved) {
                $after['parent_id'] = TelawahItemGroups::rootOf($groupId);
            }

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_telawah_telawah')->where('id', $row->id)->update($after);

            if ($moved) {
                if ($oldGroupId > 0) {
                    $main->table('nuke_telawah_groups')->where('id', $oldGroupId)->where('telawah', '>', 0)->decrement('telawah');
                }
                $main->table('nuke_telawah_groups')->where('id', $groupId)->increment('telawah');
            }

            $this->audit->record($actor, 'edit_item', 'telawah', 'item', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
                'moved' => $moved ? ['from_group' => $oldGroupId, 'to_group' => $groupId, 'parent_id' => $after['parent_id']] : null,
            ], $ip);
        });
    }

    /** An unchanged prefill keeps the stored bytes; anything else is MB → bytes, as OLD. */
    private static function linksize(mixed $megabytes, int $storedBytes): int
    {
        if (trim((string) ($megabytes ?? '')) === self::sizeField($storedBytes)) {
            return $storedBytes;
        }

        return InsertTelawahItemAction::bytes($megabytes);
    }
}
