<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — approve a request by adding its promotional
 * file. Replaces OLD `InsertAdvFile` + `ConfirmInsertAdvFile`
 * (`modules/advfiles/admin/index.php:50-109`):
 *
 * - only a pending item (-1) can be approved. OLD's form refused state 0
 *   ("يجب أن يتم التقدم بطلب ملف دعائي للمادة أولاً") and sent a published
 *   item to edit instead; ConfirmInsert's own missing check is not reproduced;
 * - the payload row is written (`id`, `credit`, `code`, `audio_url`), the item
 *   becomes `advfile = 1`, and the author / group / series `advfile`
 *   counters each move +1.
 *
 * Corrected: when a stale payload row already exists for the item (3 local
 * rows sit on unpublished items), OLD's INSERT failed on the unique key but
 * the item was still published with the OLD payload. Here the submitted
 * payload replaces it, so what the approver entered is what is published.
 */
final class ApproveAdvfileAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array{credit: int, code: string, audio_url: string} $payload */
    public function execute(AdminUser $actor, KhotabItem $item, array $payload, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.addadv', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $payload, $ip) {
            $main = DB::connection('main');
            $row = AdvfileItemLock::lock($item);
            $state = (int) $row->advfile;

            if (AdvfileState::isPublished($state)) {
                throw new AdvfileTransitionRefused('هذه المادة لها ملف دعائي منشور بالفعل — استخدم التعديل');
            }
            if ($state !== AdvfileState::PENDING) {
                throw new AdvfileTransitionRefused('عفواً ... يجب أن يتم التقدم بطلب ملف دعائي للمادة أولاً');
            }

            $stale = $main->table('nuke_islamic_advfile')->where('id', $row->id)->lockForUpdate()->exists();
            if ($stale) {
                $main->table('nuke_islamic_advfile')->where('id', $row->id)->update($payload);
            } else {
                $main->table('nuke_islamic_advfile')->insert(['id' => (int) $row->id] + $payload);
            }

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['advfile' => AdvfileState::PUBLISHED]);
            $counters = AdvfileParentCounters::move(AdvfileItemLock::parents($row), +1);

            $this->audit->record($actor, 'add_advfile', 'khotab', 'item', (int) $row->id, [
                'advfile_before' => $state,
                'advfile_after' => AdvfileState::PUBLISHED,
                'credit' => $payload['credit'],
                'code' => AdvfileAuditValue::code($payload['code']),
                'audio_url' => AdvfileAuditValue::text($payload['audio_url']),
                'replaced_stale_row' => $stale,
                'counters' => $counters,
            ], $ip);
        });
    }
}
