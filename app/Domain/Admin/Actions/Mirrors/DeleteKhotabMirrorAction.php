<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Khotab mirror delete. Replaces OLD `ConfirmDeleteMirror`
 * (`modules/Khotab/admin/index.php:671-704`).
 *
 * Physical delete plus `parent.mirror - 1`, as OLD did. `lastmirror` is
 * left alone (OLD never touched it on delete), and the `nuke_islamic_advanced_m`
 * row sharing this id is not cascaded (OLD did not cascade; 3E.1 C9).
 *
 * **Orphan-safe** (3E.1 I-4, C11). OLD looked the parent up first and
 * returned early when it was missing — so the 4,059 Khotab orphans in
 * production are undeletable through OLD's normal path. Here the delete
 * always proceeds; only the counter decrement and the item-level audit row
 * are skipped when there is no parent to apply them to. This is
 * robustness, not an orphan-cleanup feature: nothing here finds or lists
 * orphans.
 */
final class DeleteKhotabMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, Mirror $mirror, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.delmirror', $mirror);

        DB::connection('main')->transaction(function () use ($actor, $mirror, $ip) {
            /** @var Mirror $locked */
            $locked = Mirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $mirrorId = (int) $locked->id;
            $itemId = (int) $locked->khid;
            $parentExists = $itemId > 0 && KhotabItem::query()->whereKey($itemId)->exists();

            $snapshot = [
                'link' => $locked->link,
                'comment' => $locked->comment,
                'linksize' => (int) $locked->linksize,
                'vedio' => (int) $locked->vedio,
                'backupme' => (int) $locked->backupme,
            ];

            $locked->delete();

            if ($parentExists) {
                MirrorParentCounter::decrement('nuke_islamic_khotab', $itemId);
            }

            $this->audit->record($actor, 'del_mirror', 'khotab', 'mirror', $mirrorId, [
                'item_id' => $itemId,
                'parent_missing' => ! $parentExists,
                'deleted' => $snapshot,
            ], $ip);

            if ($parentExists) {
                $this->audit->record($actor, 'del_mirror', 'khotab', 'item', $itemId, [
                    'mirror_id' => $mirrorId,
                ], $ip);
            }
        });
    }
}
