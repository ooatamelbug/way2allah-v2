<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 6 — delete a CD release. Replaces OLD
 * `DeleteW2ACD` + `ConfirmDeleteW2ACD` (`:564-590`): the release row is
 * deleted and its group's `w2acd` counter moves −1 (floored). As in OLD, its
 * image files, comments and the normalized mirror table are not touched.
 * The audit row keeps the full release so a deletion is reconstructible.
 */
final class DeleteCdReleaseAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, W2acdItem $release, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $release);

        DB::connection('main')->transaction(function () use ($actor, $release, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_w2acd')->where('id', $release->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);

            $main->table('nuke_w2acd_w2acd')->where('id', $row->id)->delete();
            $counter = CdGroupCounter::move((int) $row->group_id, -1);

            $this->audit->record($actor, 'del_cd', 'w2acd', 'cd_release', (int) $row->id, [
                'deleted' => [
                    'title' => CdAuditText::of($row->title),
                    'group_id' => (int) $row->group_id,
                    'links' => CdAuditText::links(CdLinkList::parse($row->link, $row->cd)),
                    'images' => CdImageList::parse($row->thumbnail),
                    'mytime' => (int) $row->mytime,
                    'hits' => (int) $row->hits,
                    'order_in_group' => (int) $row->order_in_group,
                ],
                'group_counter' => $counter,
            ], $ip);
        });
    }
}
