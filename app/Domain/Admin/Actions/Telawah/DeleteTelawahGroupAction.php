<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «حذف القسم» — OLD `TelawahDeleteGroup` → `ConfirmDeleteGroup`
 * (`modules/Telawah/admin/index.php`:556-610, production-hash-verified).
 *
 * OLD refused while any recitation had `group_id = X` («يجب عليك أولا مسح
 * جميع الخطب و السلاسل الخاصة بهذه القسم أولا من الموقع»); otherwise it
 * deleted the row and took 1 from the parent's maintained `child` counter.
 *
 * **Owner-approved correction (2026-10-29):** OLD never checked sub-groups,
 * so deleting a group that had them left those children pointing at a
 * missing parent. Laravel also refuses while any group has `parent_id = X`.
 * Neither recitations nor sub-groups are ever cascade-deleted.
 *
 * Other corrections: authorized (`telawah.delgroup`); both checks, the
 * delete, the counter and the audit row in one transaction; the counter is
 * never taken below 0 (OLD: `child = child - 1` unconditionally).
 */
final class DeleteTelawahGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @return array{parent_id: int, title: string}
     *
     * @throws ValidationException a group that still has recitations or sub-groups
     */
    public function execute(AdminUser $actor, TelawahGroup $group, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'telawah.delgroup', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_telawah_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'title', 'parent_id']);
            abort_if($row === null, 404);

            $title = (string) $row->title;

            if ($main->table('nuke_telawah_telawah')->where('group_id', $row->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح جميع الخطب و السلاسل الخاصة بهذه القسم أولا من الموقع"]);
            }

            if ($main->table('nuke_telawah_groups')->where('parent_id', $row->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الأقسام الفرعية الخاصة بهذا القسم"]);
            }

            $parentId = (int) $row->parent_id;
            $main->table('nuke_telawah_groups')->where('id', $row->id)->delete();
            if ($parentId > 0) {
                $main->table('nuke_telawah_groups')->where('id', $parentId)->where('child', '>', 0)->decrement('child');
            }

            $this->audit->record($actor, 'del_group', 'telawah', 'group', (int) $row->id, [
                'parent_id' => $parentId,
            ], $ip);

            return ['parent_id' => $parentId, 'title' => $title];
        });
    }
}
