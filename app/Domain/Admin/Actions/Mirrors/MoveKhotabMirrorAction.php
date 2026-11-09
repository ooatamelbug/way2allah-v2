<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 1 — Khotab mirror re-parent. Replaces OLD `MoveMirror`
 * (`modules/Khotab/admin/index.php:342-372`). Khotab only — Anasheed never
 * had a move operation.
 *
 * **Permission is `khotab.editmirror`**, exactly the token OLD checked
 * (`GreenZone('editmirror')`, `:346`). No `movemirror` permission exists
 * (plan correction 4).
 *
 * OLD's three statements, now one transaction: `SET khid = target`,
 * `target.mirror + 1`, `old_parent.mirror - 1`. `lastmirror` is touched on
 * neither parent — OLD did not, and a move is not a new mirror. No
 * duplicate check (OLD had none here).
 *
 * The two-step interaction OLD implemented as a domain-wide cookie holding
 * a serialized database row (`MirrorMove`, `modules/Khotab/index.php:1855-1866`)
 * is kept as an interaction — pick here, place on the target item's page —
 * but its state is a mirror id in the Laravel session, held by the
 * controller. This action needs no clipboard at all: it takes the mirror
 * and the target explicitly (3E.1 I-11).
 *
 * Orphan-safe: a mirror whose current parent no longer exists can be moved
 * onto a real item, which is the only way OLD could ever rescue one. The
 * source decrement is simply skipped.
 */
final class MoveKhotabMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(AdminUser $actor, Mirror $mirror, KhotabItem $target, ?string $ip = null): Mirror
    {
        $this->authorizer->authorize($actor, 'khotab.editmirror', $mirror);

        return DB::connection('main')->transaction(function () use ($actor, $mirror, $target, $ip) {
            /** @var Mirror $locked */
            $locked = Mirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $fromItemId = (int) $locked->khid;
            $toItemId = (int) $target->id;

            if ($fromItemId === $toItemId) {
                throw ValidationException::withMessages([
                    'target_item_id' => 'هذه الوصلة تابعة لهذه المادة بالفعل.',
                ]);
            }

            $fromParentExists = $fromItemId > 0 && KhotabItem::query()->whereKey($fromItemId)->exists();

            $locked->forceFill(['khid' => $toItemId])->save();

            MirrorParentCounter::increment('nuke_islamic_khotab', $toItemId);

            if ($fromParentExists) {
                MirrorParentCounter::decrement('nuke_islamic_khotab', $fromItemId);
            }

            $this->audit->record($actor, 'move_mirror', 'khotab', 'mirror', (int) $locked->id, [
                'from_item_id' => $fromItemId,
                'to_item_id' => $toItemId,
                'from_parent_missing' => ! $fromParentExists,
            ], $ip);

            return $locked;
        });
    }
}
