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
 * Phase 1 — Khotab mirror insert. Replaces OLD `ConfirmInsertMirror`
 * (`modules/Khotab/admin/index.php:471-545`).
 *
 * Writes exactly the columns OLD wrote — `khid`, `link`, `comment`,
 * `linksize`, `vedio`, `backupme`, `time` — and nothing else. Every
 * quality/backup column (`down`, `trial`, `online`, `percent`,
 * `checktime`, `booking`, `downloader`, `hits`) is left to its table
 * default, as OLD left it (3E.1 invariants I-6, I-7, I-9). In production
 * that default is `down = 2, trial = 0`, so a new mirror is immediately
 * backup-eligible — that is intended, and setting any of those columns
 * here would silently change backup enrolment.
 */
final class InsertKhotabMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{link: string, comment?: string|null, linksize_mb?: mixed, vedio: int|string, backupme?: mixed}  $data
     */
    public function execute(AdminUser $actor, KhotabItem $item, array $data, ?string $ip = null): Mirror
    {
        $this->authorizer->authorize($actor, 'khotab.addmirror', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $data, $ip) {
            $link = (string) $data['link'];

            $this->guardAgainstAuthorDuplicate($item, $link);

            $time = time();

            $mirror = new Mirror;
            $mirror->forceFill([
                'khid' => (int) $item->id,
                'link' => $link,
                'comment' => (string) ($data['comment'] ?? ''),
                'linksize' => MirrorParentCounter::megabytesToBytes($data['linksize_mb'] ?? 0),
                'vedio' => (int) $data['vedio'],
                'backupme' => empty($data['backupme']) ? 0 : 1,
                'time' => $time,
            ])->save();

            MirrorParentCounter::increment('nuke_islamic_khotab', (int) $item->id, $time);

            // Dual-row convention (3E.2 §8.2.2): one row for the mirror —
            // carrying the *mirror's own* id, which OLD got wrong — and one
            // for the item, so "what happened to this item" is answerable
            // from the entity index alone.
            $this->audit->record($actor, 'add_mirror', 'khotab', 'mirror', (int) $mirror->id, [
                'item_id' => (int) $item->id,
                'link' => $link,
                'comment' => $mirror->comment,
                'linksize' => (int) $mirror->linksize,
                'vedio' => (int) $mirror->vedio,
                'backupme' => (int) $mirror->backupme,
            ], $ip);

            $this->audit->record($actor, 'add_mirror', 'khotab', 'item', (int) $item->id, [
                'mirror_id' => (int) $mirror->id,
            ], $ip);

            return $mirror;
        });
    }

    /**
     * OLD's one duplicate guard (3E.1 §3.5), reproduced exactly, and only
     * on this insert path — OLD never checked on edit, re-parent, merge or
     * the backup tool, and neither does Laravel.
     *
     * Scope: every mirror whose parent item has the same author, across all
     * of that author's items, series and groups. The inner join means
     * orphan mirrors are invisible to it, as they were to OLD.
     *
     * `(int) ($item->author ?? 0)` is OLD's own coercion. Production's
     * `author` column is nullable, and OLD turned NULL into `0` before
     * comparing — so a NULL-author item is checked against literal-0
     * authors and never against other NULL-author items. That gap is
     * reproduced rather than closed, because closing it would be new
     * duplicate rejection the contract does not authorize.
     *
     * Comparison is left to the database (`=` under the column's own
     * collation), exactly as OLD's SQL did.
     */
    private function guardAgainstAuthorDuplicate(KhotabItem $item, string $link): void
    {
        $existing = DB::connection('main')->table('nuke_islamic_mirror as m')
            ->join('nuke_islamic_khotab as k', 'm.khid', '=', 'k.id')
            ->where('m.link', $link)
            ->where('k.author', (int) ($item->author ?? 0))
            ->select(['m.id', 'm.khid'])
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'link' => "هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة #{$existing->khid} (الوصلة #{$existing->id}).",
            ]);
        }
    }
}
