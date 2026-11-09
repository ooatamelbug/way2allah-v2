<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — reject (cancel) a pending request. Replaces
 * OLD `ResetAdvFile()` (`modules/advfiles/admin/index.php:219-233`), the
 * delete icon of the pending queue (`advfiles_cont.htm`): `advfile` -1 → 0.
 *
 * OLD refused a published item ("يوجد ملف دعائي لهذا الدرس بالفعل"); that is
 * reproduced. OLD's Reset otherwise wrote 0 from any other state, which for
 * the unexplained state 2 would hide a published file while leaving its
 * payload and counters behind; here only a pending request can be rejected,
 * and rejecting an item that is not pending (0) is a no-op.
 */
final class RejectAdvfileRequestAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return bool whether anything changed */
    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, 'khotab.addadv', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $row = AdvfileItemLock::lock($item);
            $state = (int) $row->advfile;

            if (AdvfileState::isPublished($state)) {
                throw new AdvfileTransitionRefused('يوجد ملف دعائي لهذا الدرس بالفعل');
            }
            if ($state !== AdvfileState::PENDING) {
                return false;
            }

            DB::connection('main')->table('nuke_islamic_khotab')->where('id', $row->id)->update(['advfile' => AdvfileState::NONE]);

            $this->audit->record($actor, 'reset_advfile', 'khotab', 'item', (int) $row->id, [
                'advfile_before' => $state,
                'advfile_after' => AdvfileState::NONE,
            ], $ip);

            return true;
        });
    }
}
