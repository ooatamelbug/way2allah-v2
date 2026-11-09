<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — bulk credit edit. Replaces OLD `Credit()` /
 * `ConfirmCredit()` (`modules/advfiles/admin/index.php:235-271`): the list
 * of payloads `WHERE credit > 0 ORDER BY credit DESC`, each credit editable.
 *
 * `credit` is labelled "remaining display count" but nothing in any codebase
 * ever decrements it (Pass 3A §4); it is reproduced exactly as OLD used it —
 * an admin-set number the list is ordered by. No metering is invented.
 *
 * Corrected: OLD wrote `credit` for whatever ids the request array named
 * (Pass 3A A-6). Here only existing payload rows are updated, only when the
 * value changes, one audit row per changed payload.
 */
final class UpdateAdvfileCreditsAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<int, int>  $credits  item id => credit
     * @return int the number of payloads changed
     */
    public function execute(AdminUser $actor, array $credits, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addadv');

        return DB::connection('main')->transaction(function () use ($actor, $credits, $ip) {
            $main = DB::connection('main');
            $rows = $main->table('nuke_islamic_advfile')->whereIn('id', array_keys($credits))->orderBy('id')->lockForUpdate()->get(['id', 'credit']);
            $changed = 0;

            foreach ($rows as $row) {
                $before = (int) $row->credit;
                $after = $credits[(int) $row->id];
                if ($before === $after) {
                    continue;
                }
                $main->table('nuke_islamic_advfile')->where('id', $row->id)->update(['credit' => $after]);
                $this->audit->record($actor, 'credit_advfile', 'khotab', 'item', (int) $row->id, [
                    'credit_before' => $before,
                    'credit_after' => $after,
                ], $ip);
                $changed++;
            }

            return $changed;
        });
    }
}
