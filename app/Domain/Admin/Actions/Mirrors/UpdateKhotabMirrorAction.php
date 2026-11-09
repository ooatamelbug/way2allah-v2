<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Mirror;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Khotab mirror edit. Replaces OLD `ConfirmEditMirror`
 * (`modules/Khotab/admin/index.php:596-640`).
 *
 * Writes OLD's exact set: `comment`, `link`, `vedio`, `backupme`, and
 * `trial = 0`. The trial reset is how a corrected link re-enters the
 * backup queue (`down = 2 AND trial < max`), so dropping it would leave a
 * fixed link permanently excluded (3E.1 C3). **`linksize` is not written**
 * — Khotab's edit never touched it, unlike Anasheed's (3E.1 C10).
 *
 * No duplicate check: OLD had none on edit, and the contract does not add
 * one. No parent work: an edit never changes ownership.
 *
 * Works on orphans (3E.1 I-4). OLD refused to edit a mirror whose parent
 * was missing; nothing here needs the parent, so Laravel does not.
 */
final class UpdateKhotabMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{link?: string|null, comment?: string|null, vedio: int|string, backupme?: mixed}  $data
     */
    public function execute(AdminUser $actor, Mirror $mirror, array $data, ?string $ip = null): Mirror
    {
        $this->authorizer->authorize($actor, 'khotab.editmirror', $mirror);

        return DB::connection('main')->transaction(function () use ($actor, $mirror, $data, $ip) {
            // Re-read under a row lock: OLD and Laravel can both write this
            // row during coexistence, and the "before" values in the audit
            // must be the ones actually replaced.
            /** @var Mirror $locked */
            $locked = Mirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $before = $this->snapshot($locked);

            $locked->forceFill([
                'comment' => (string) ($data['comment'] ?? ''),
                'link' => (string) ($data['link'] ?? ''),
                'vedio' => (int) $data['vedio'],
                'backupme' => empty($data['backupme']) ? 0 : 1,
                'trial' => 0,
            ])->save();

            $this->audit->record($actor, 'edit_mirror', 'khotab', 'mirror', (int) $locked->id, [
                'item_id' => (int) $locked->khid,
                'changes' => $this->diff($before, $this->snapshot($locked)),
            ], $ip);

            return $locked;
        });
    }

    /**
     * The audited columns, normalised so a string-typed `'0'` from the
     * driver never registers as a change against an int `0`.
     *
     * @return array<string, int|string|null>
     */
    private function snapshot(Mirror $mirror): array
    {
        return [
            'link' => $mirror->link,
            'comment' => $mirror->comment,
            'vedio' => (int) $mirror->vedio,
            'backupme' => (int) $mirror->backupme,
            'trial' => (int) $mirror->trial,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $column => $value) {
            if ($before[$column] !== $value) {
                $changes[$column] = ['from' => $before[$column], 'to' => $value];
            }
        }

        return $changes;
    }
}
