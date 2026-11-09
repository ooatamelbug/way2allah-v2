<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — remove a published promotional file.
 * Replaces OLD `DeleteAdvFile` + `ConfirmDeleteAdvFile`
 * (`modules/advfiles/admin/index.php:130-195`): the payload row is deleted,
 * the item returns to `advfile = 0`, and the author / group / series
 * counters each move -1 (floored at 0).
 *
 * Requires a published item. OLD keyed the operation on the payload row
 * instead, so it could not unpublish a published item whose row was missing
 * (8 local items) and would decrement counters for a row on an unpublished
 * item (3 local rows). Here the published state is what is removed; the row
 * is deleted if present; counters move only because a published item stops
 * being published.
 *
 * POST/DELETE with CSRF — OLD's confirm step was a GET-reachable URL.
 */
final class UnpublishAdvfileAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.addadv', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $main = DB::connection('main');
            $row = AdvfileItemLock::lock($item);
            $state = (int) $row->advfile;

            if (! AdvfileState::isPublished($state)) {
                throw new AdvfileTransitionRefused('لا يوجد ملف دعائي منشور لهذه المادة');
            }

            $file = $main->table('nuke_islamic_advfile')->where('id', $row->id)->lockForUpdate()->first();
            $main->table('nuke_islamic_advfile')->where('id', $row->id)->delete();
            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['advfile' => AdvfileState::NONE]);
            $counters = AdvfileParentCounters::move(AdvfileItemLock::parents($row), -1);

            $this->audit->record($actor, 'del_advfile', 'khotab', 'item', (int) $row->id, [
                'advfile_before' => $state,
                'advfile_after' => AdvfileState::NONE,
                'deleted' => $file === null ? null : [
                    'credit' => (int) $file->credit,
                    'code' => AdvfileAuditValue::code($file->code),
                    'audio_url' => AdvfileAuditValue::text($file->audio_url),
                    'small_thumb' => (int) $file->small_thumb,
                    'large_thumb' => (int) $file->large_thumb,
                ],
                'counters' => $counters,
            ], $ip);
        });
    }
}
