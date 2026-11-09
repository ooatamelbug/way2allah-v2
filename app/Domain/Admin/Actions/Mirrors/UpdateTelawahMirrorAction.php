<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahMirror;
use Illuminate\Support\Facades\DB;

/**
 * Telawah mirror edit (closure correction, 2026-10-15). Replaces OLD
 * `ConfirmEditMirror` (`modules/Telawah/admin/index.php:1529-1553`).
 *
 * Writes exactly OLD's set: `comment`, `video`, `backupme`, `link`. Nothing
 * else, by OLD's own contract and by the schema:
 *
 * - no `trial` reset — the table has no `trial` (Pass 3E.1 §3.2);
 * - `size` is never edited — OLD's edit form has no size field;
 * - `extension` and `creation_date` are left as they are;
 * - no parent counter — `nuke_telawah_telawah` has none.
 *
 * Corrections, as Phase 1: OLD's `GreenZone()` and `RecordLog()` calls here
 * are commented out, so OLD edited unauthorized and unlogged. Laravel
 * authorizes (`telawah.editmirror`) and writes the structured audit row
 * inside the transaction.
 */
final class UpdateTelawahMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{link?: string|null, comment?: string|null, vedio: int|string, backupme?: mixed}  $data
     */
    public function execute(AdminUser $actor, TelawahMirror $mirror, array $data, ?string $ip = null): TelawahMirror
    {
        $this->authorizer->authorize($actor, 'telawah.editmirror', $mirror);

        return DB::connection('main')->transaction(function () use ($actor, $mirror, $data, $ip) {
            /** @var TelawahMirror $locked */
            $locked = TelawahMirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $before = $this->snapshot($locked);

            $locked->forceFill([
                'comment' => (string) ($data['comment'] ?? ''),
                'link' => (string) ($data['link'] ?? ''),
                'video' => (string) (int) $data['vedio'],
                'backupme' => empty($data['backupme']) ? '0' : '1',
            ])->save();

            $changes = [];
            foreach ($this->snapshot($locked) as $column => $value) {
                if ($before[$column] !== $value) {
                    $changes[$column] = ['from' => $before[$column], 'to' => $value];
                }
            }

            $this->audit->record($actor, 'edit_mirror', 'telawah', 'mirror', (int) $locked->id, [
                'item_id' => (int) $locked->telawa_id,
                'changes' => $changes,
            ], $ip);

            return $locked;
        });
    }

    /** @return array<string, string|null> */
    private function snapshot(TelawahMirror $mirror): array
    {
        return [
            'comment' => $mirror->comment,
            'link' => $mirror->link,
            'video' => $mirror->video === null ? null : (string) $mirror->video,
            'backupme' => (string) $mirror->backupme,
        ];
    }
}
