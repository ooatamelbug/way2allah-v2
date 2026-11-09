<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * G5 «حذف القسم» — OLD `W2ACDDeleteGroup` → `ConfirmDeleteGroup`
 * (`modules/w2acd/admin/index.php`).
 *
 * OLD refused while any CD had `group_id = X` (its message template,
 * `delgrouperror.htm`, is missing, so the refusal printed an error page);
 * otherwise it deleted the row. It meant to take 1 from the parent's `child`
 * counter and return to the parent group, but `$Group` was never loaded, so
 * neither happened and it always went to `cds-main`.
 *
 * **Safety correction (owner, 2026-11-01; the semantic the owner approved for
 * Anasheed groups on 2026-10-23, `DeleteAnasheedGroupAction`).** OLD never
 * checked sub-groups, so deleting a group that had them left them pointing at
 * a missing parent. Laravel also refuses while any group has `parent_id = X`.
 * Neither CDs nor sub-groups are ever cascade-deleted, and the icon file is
 * kept, as OLD kept it.
 *
 * Other corrections: authorized (`w2acd.managecds`); a real refusal message;
 * the parent's `child` counter is taken down (never below 0); both checks,
 * the delete, the counter and the audit row in one transaction.
 */
final class DeleteCdGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @return array{parent_id: int}
     *
     * @throws ValidationException a group that still has CDs or sub-groups
     */
    public function execute(AdminUser $actor, W2acdGroup $group, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'title', 'parent_id']);
            abort_if($row === null, 404);

            $title = (string) $row->title;

            if ($main->table('nuke_w2acd_w2acd')->where('group_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الإسطوانات الخاصة بهذا القسم"]);
            }

            if ($main->table('nuke_w2acd_groups')->where('parent_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['group' => "عفواً .... لن تتمكن من مسح '' {$title} '' من قاعدة بيانات الموقع - يجب عليك أولا مسح أو نقل جميع الأقسام الفرعية الخاصة بهذا القسم"]);
            }

            $parentId = (int) $row->parent_id;
            $main->table('nuke_w2acd_groups')->where('id', $group->id)->delete();
            if ($parentId > 0) {
                $main->table('nuke_w2acd_groups')->where('id', $parentId)->where('child', '>', 0)->decrement('child');
            }

            $this->audit->record($actor, 'del_group', 'w2acd', 'cd_group', (int) $group->id, [
                'parent_id' => $parentId,
                'title' => $title,
            ], $ip);

            return ['parent_id' => $parentId];
        });
    }
}
