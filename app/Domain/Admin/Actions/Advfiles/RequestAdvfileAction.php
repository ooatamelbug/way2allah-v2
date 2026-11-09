<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — "طلب رئيسية": request a promotional file for
 * an item. Replaces OLD `RequestAdvFile()` (`modules/Khotab/admin/index.php:384-423`):
 *
 * - published (`advfile > 0`) → refused, OLD's own message;
 * - none (0) → `advfile = -1`, audited `request_adv` (OLD's legacy action);
 * - already pending (-1) → no-op. OLD re-wrote -1 and logged again; here
 *   nothing is written and nothing is audited.
 *
 * OLD let any administrator request (`GreenZone('AdvFile')` is a no-op;
 * only `is_w2a_admin()` ran), so this action needs only an authenticated
 * admin — the route's `admin.role` gate. Approval is the guarded step.
 */
final class RequestAdvfileAction
{
    public const REQUESTED = 'requested';

    public const ALREADY_PENDING = 'already_pending';

    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): string
    {
        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $row = AdvfileItemLock::lock($item);
            $state = (int) $row->advfile;

            if (AdvfileState::isPublished($state)) {
                throw new AdvfileTransitionRefused('عفواً .... هذه المادة لها ملف دعائي بالفعل و لا يمكن تقديم طلب ملف دعائي مرة أخرى');
            }
            if ($state === AdvfileState::PENDING) {
                return self::ALREADY_PENDING;
            }
            if ($state !== AdvfileState::NONE) {
                throw new AdvfileTransitionRefused('حالة الملف الدعائي لهذه المادة غير معروفة');
            }

            DB::connection('main')->table('nuke_islamic_khotab')->where('id', $row->id)->update(['advfile' => AdvfileState::PENDING]);

            $this->audit->record($actor, 'request_adv', 'khotab', 'item', (int) $row->id, [
                'advfile_before' => $state,
                'advfile_after' => AdvfileState::PENDING,
            ], $ip);

            return self::REQUESTED;
        });
    }
}
