<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * G2 «تعديل القسم» — OLD `W2ACDEditGroup` → `ConfirmEditGroup`
 * (`modules/w2acd/admin/index.php`).
 *
 * Writes OLD's form columns on `nuke_w2acd_groups` — title, parent_id, des,
 * and `module_type = 'cd'` as OLD did — and, when the parent changed, OLD's
 * maintained counters: `child - 1` on the old parent, `child + 1` on the new.
 * Like OLD, nothing else moves (the CDs keep their stored `parent_id`).
 *
 * Corrections: authorized (`w2acd.managecds`; OLD's GreenZone was inert);
 * the parent must be one OLD's own select offered (CdGroupParents — OLD
 * accepted any posted id); `channel_id` is left alone (OLD's form had no
 * channel field but its UPDATE still wrote the missing value, blanking it);
 * the update, both counters and the audit row in one transaction; a counter
 * is never taken below 0.
 */
final class UpdateCdGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{title: string, parent_id: int|string, Des?: string|null}  $data  the validated form, OLD field names
     *
     * @throws ValidationException a parent OLD's select did not offer
     */
    public function execute(AdminUser $actor, W2acdGroup $group, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $group);

        DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'title', 'parent_id', 'des', 'module_type']);
            abort_if($row === null, 404);

            $current = W2acdGroup::query()->findOrFail($group->id);
            $parentId = (int) $data['parent_id'];
            if (! in_array($parentId, CdGroupParents::allowedIds($current), true)) {
                throw ValidationException::withMessages(['parent_id' => 'القسم الرئيسي المختار غير متاح لهذا القسم']);
            }

            $after = [
                'title' => (string) $data['title'],
                'parent_id' => $parentId,
                'des' => (string) ($data['Des'] ?? ''),
                'module_type' => 'cd',
            ];
            $before = ['title' => $row->title, 'parent_id' => (int) $row->parent_id, 'des' => $row->des, 'module_type' => $row->module_type];

            $main->table('nuke_w2acd_groups')->where('id', $group->id)->update($after);

            $oldParentId = (int) $row->parent_id;
            $moved = $parentId !== $oldParentId;
            if ($moved) {
                if ($oldParentId > 0) {
                    $main->table('nuke_w2acd_groups')->where('id', $oldParentId)->where('child', '>', 0)->decrement('child');
                }
                if ($parentId > 0) {
                    $main->table('nuke_w2acd_groups')->where('id', $parentId)->increment('child');
                }
            }

            $this->audit->record($actor, 'edit_group', 'w2acd', 'cd_group', (int) $group->id, [
                'changes' => AuditText::changes($before, $after, ['title', 'des']),
                'moved' => $moved ? ['from_parent' => $oldParentId, 'to_parent' => $parentId] : null,
            ], $ip);
        });
    }
}
