<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * OLD Admin Migration Phase 6 — order the releases of one group. Replaces
 * OLD `Order_w2acd_in_group()` (`:1521-1592`): for the submitted order,
 * position i of n gets `order_in_group = n − i`, so the first listed is
 * shown first by the public list (`ORDER BY order_in_group DESC, mytime
 * DESC`, `W2acdController`).
 *
 * Corrected: OLD updated whatever ids were posted (`WHERE id = …`, no group
 * check). Here the submission must name exactly the group's releases, and
 * only rows whose value changes are written — one audit row each.
 *
 * Not reproduced: OLD's `weight` re-arrange (`ReArrange`), which the public
 * pages do not read.
 */
final class ReorderCdReleasesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>  $orderedIds  first = shown first
     * @return int rows changed
     */
    public function execute(AdminUser $actor, int $groupId, array $orderedIds, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds');

        return DB::connection('main')->transaction(function () use ($actor, $groupId, $orderedIds, $ip) {
            $main = DB::connection('main');
            $rows = $main->table('nuke_w2acd_w2acd')->where('group_id', $groupId)->orderBy('id')->lockForUpdate()
                ->get(['id', 'order_in_group'])->keyBy('id');

            $submitted = array_map('intval', $orderedIds);
            $expected = $rows->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = $submitted;
            sort($given);
            if ($given !== $expected) {
                throw new InvalidArgumentException('The order must list every release of the group exactly once.');
            }

            $n = count($submitted);
            $changed = 0;
            foreach ($submitted as $i => $id) {
                $before = (int) $rows[$id]->order_in_group;
                $after = $n - $i;
                if ($before === $after) {
                    continue;
                }
                $main->table('nuke_w2acd_w2acd')->where('id', $id)->update(['order_in_group' => $after]);
                $this->audit->record($actor, 'order_cd', 'w2acd', 'cd_release', $id, [
                    'group_id' => $groupId,
                    'order_in_group_before' => $before,
                    'order_in_group_after' => $after,
                ], $ip);
                $changed++;
            }

            return $changed;
        });
    }
}
