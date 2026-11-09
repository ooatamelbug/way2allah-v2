<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahMirror;
use Illuminate\Support\Facades\DB;

/**
 * Telawah mirror delete (closure correction, 2026-10-15). Replaces OLD
 * `ConfirmDeleteMirror` (`modules/Telawah/admin/index.php:1573-1589`):
 * a physical delete and nothing more — the parent has no counter, and
 * OLD's counter update is commented out because the columns do not exist
 * (Pass 3E.1 §0.2.2).
 *
 * Corrections, as Phase 1: authorized (`telawah.delmirror`) and audited
 * inside the transaction; OLD's checks and log calls were commented out.
 */
final class DeleteTelawahMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, TelawahMirror $mirror, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'telawah.delmirror', $mirror);

        DB::connection('main')->transaction(function () use ($actor, $mirror, $ip) {
            /** @var TelawahMirror $locked */
            $locked = TelawahMirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $mirrorId = (int) $locked->id;
            $snapshot = [
                'comment' => $locked->comment,
                'link' => $locked->link,
                'size' => $locked->size === null ? null : (int) $locked->size,
                'video' => $locked->video,
                'backupme' => $locked->backupme,
            ];
            $itemId = (int) $locked->telawa_id;

            $locked->delete();

            $this->audit->record($actor, 'del_mirror', 'telawah', 'mirror', $mirrorId, [
                'item_id' => $itemId,
                'deleted' => $snapshot,
            ], $ip);
        });
    }
}
