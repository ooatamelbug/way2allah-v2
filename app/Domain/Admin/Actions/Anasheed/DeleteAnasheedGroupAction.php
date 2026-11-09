<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «حذف القسم» — OLD `AnasheedDeleteGroup` → `ConfirmDeleteGroup`
 * (`modules/Anasheed/admin/index.php`, HEAD:891-925).
 *
 * OLD refused while any item had `group_id = X` («يجب عليك أولا مسح جميع
 * الخطب و السلاسل الخاصة بهذه القسم أولا من الموقع»); otherwise it deleted the
 * row and took 1 from the parent's maintained `child` counter.
 *
 * **Safety correction (owner, 2026-10-23).** OLD never checked sub-groups,
 * so deleting a group that had them left those children pointing at a
 * missing parent. Laravel also refuses while any group has `parent_id = X`.
 * Neither items nor sub-groups are ever cascade-deleted.
 *
 * Other corrections: authorized (`anasheed.delgroup`); both checks, the
 * delete, the counter and the audit row in one transaction; the counter is
 * never taken below 0.
 */
final class DeleteAnasheedGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @return array{parent_id: int}
     *
     * @throws ValidationException a group that still has items or sub-groups
     */
    public function execute(AdminUser $actor, AnasheedGroup $group, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'anasheed.delgroup', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'title', 'parent_id']);
            abort_if($row === null, 404);

            $title = (string) $row->title;

            if ($main->table('nuke_anasheed_anasheed')->where('group_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح جميع الخطب و السلاسل الخاصة بهذه القسم أولا من الموقع"]);
            }

            if ($main->table('nuke_anasheed_groups')->where('parent_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الأقسام الفرعية الخاصة بهذا القسم"]);
            }

            $parentId = (int) $row->parent_id;
            $main->table('nuke_anasheed_groups')->where('id', $group->id)->delete();
            if ($parentId > 0) {
                $main->table('nuke_anasheed_groups')->where('id', $parentId)->where('child', '>', 0)->decrement('child');
            }

            $this->audit->record($actor, 'del_group', 'anasheed', 'group', (int) $group->id, [
                'parent_id' => $parentId,
            ], $ip);

            return ['parent_id' => $parentId];
        });
    }
}
