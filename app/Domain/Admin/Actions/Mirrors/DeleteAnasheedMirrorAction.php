<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Anasheed mirror delete. Replaces OLD `ConfirmDeleteMirror`
 * (`modules/Anasheed/admin/index.php:330-350`).
 *
 * Physical delete plus `parent.mirror - 1`, orphan-safe in the same way as
 * Khotab's delete (3E.1 I-4): production holds 480 Anasheed orphan mirrors,
 * and the delete proceeds for them with the decrement and the item-level
 * audit row skipped. `lastmirror` is untouched, as in OLD.
 */
final class DeleteAnasheedMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, AnasheedMirror $mirror, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'anasheed.delmirror', $mirror);

        DB::connection('main')->transaction(function () use ($actor, $mirror, $ip) {
            /** @var AnasheedMirror $locked */
            $locked = AnasheedMirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $mirrorId = (int) $locked->id;
            $itemId = (int) $locked->khid;
            $parentExists = $itemId > 0 && AnasheedItem::query()->whereKey($itemId)->exists();

            $snapshot = [
                'link' => $locked->link,
                'title' => $locked->title,
                'linksize' => (int) $locked->linksize,
                'vedio' => (int) $locked->vedio,
                'backupme' => (int) $locked->backupme,
            ];

            $locked->delete();

            if ($parentExists) {
                MirrorParentCounter::decrement('nuke_anasheed_anasheed', $itemId);
            }

            $this->audit->record($actor, 'del_mirror', 'anasheed', 'mirror', $mirrorId, [
                'item_id' => $itemId,
                'parent_missing' => ! $parentExists,
                'deleted' => $snapshot,
            ], $ip);

            if ($parentExists) {
                $this->audit->record($actor, 'del_mirror', 'anasheed', 'item', $itemId, [
                    'mirror_id' => $mirrorId,
                ], $ip);
            }
        });
    }
}
