<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نقل إلى قسم شيخ أخر» — OLD `TelawahmoveTelawah()`
 * (`modules/Telawah/admin/index.php`:1398-1430, `move_telawa_form.htm`,
 * production-hash-verified). OLD's form chooses a reciter (a root group) and,
 * optionally, one of its direct sub-groups; the destination is the sub-group
 * when chosen, else the reciter.
 *
 * **Owner-approved correction (2026-10-29):** OLD wrote `group_id` only — no
 * counters, no `parent_id`, no check that the destination exists (choosing
 * nothing set `group_id = 0`). Laravel validates the destination, then in one
 * locked transaction updates `group_id`, recomputes `parent_id`
 * (TelawahItemGroups::rootOf), takes 1 from the old group's `telawah`
 * (floored at 0) and adds 1 to the destination's. Choosing the item's own
 * group changes nothing and is not audited.
 *
 * Authorized with `telawah.edittelawah` (owner mapping: OLD had no move
 * token and checked nothing).
 */
final class MoveTelawahItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @return array{moved: bool, group_id: int}
     *
     * @throws ValidationException a destination OLD's selects did not offer
     */
    public function execute(AdminUser $actor, TelawahItem $item, int $reciterId, int $subGroupId, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'telawah.edittelawah', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $reciterId, $subGroupId, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_telawah_telawah')->where('id', $item->id)->lockForUpdate()->first(['id', 'group_id', 'parent_id']);
            abort_if($row === null, 404);

            if (! $main->table('nuke_telawah_groups')->where('id', $reciterId)->where('parent_id', 0)->exists()) {
                throw ValidationException::withMessages(['group_id' => 'من فضلك إختر الشيخ']);
            }
            if ($subGroupId > 0 && ! $main->table('nuke_telawah_groups')->where('id', $subGroupId)->where('parent_id', $reciterId)->exists()) {
                throw ValidationException::withMessages(['sub_group_id' => 'القسم المختار لا يتبع هذا الشيخ']);
            }

            $groupId = $subGroupId > 0 ? $subGroupId : $reciterId;
            $oldGroupId = (int) $row->group_id;
            if ($groupId === $oldGroupId) {
                return ['moved' => false, 'group_id' => $groupId];
            }

            $parentId = TelawahItemGroups::rootOf($groupId);
            $main->table('nuke_telawah_telawah')->where('id', $row->id)->update(['group_id' => $groupId, 'parent_id' => $parentId]);
            if ($oldGroupId > 0) {
                $main->table('nuke_telawah_groups')->where('id', $oldGroupId)->where('telawah', '>', 0)->decrement('telawah');
            }
            $main->table('nuke_telawah_groups')->where('id', $groupId)->increment('telawah');

            $this->audit->record($actor, 'move_item', 'telawah', 'item', (int) $row->id, [
                'group_id' => ['from' => $oldGroupId, 'to' => $groupId],
                'parent_id' => ['from' => (int) $row->parent_id, 'to' => $parentId],
            ], $ip);

            return ['moved' => true, 'group_id' => $groupId];
        });
    }
}
