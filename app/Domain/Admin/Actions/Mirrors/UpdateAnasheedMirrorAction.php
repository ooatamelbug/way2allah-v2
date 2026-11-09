<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedMirror;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Anasheed mirror edit. Replaces OLD `ConfirmEditMirror`
 * (`modules/Anasheed/admin/index.php:272-306`).
 *
 * Writes OLD's set: `title`, `link`, `vedio`, `backupme`, `trial = 0`, and
 * — unlike Khotab — `linksize` (3E.1 C10).
 *
 * **The `linksize` rule is reproduced exactly as OLD executed it, and it
 * is strange in two ways.**
 *
 * 1. *Unit.* OLD's edit form pre-fills the **stored byte value** and the
 *    handler only `intval()`s it — no megabyte conversion. Insert takes
 *    megabytes; edit takes bytes. That is a UI-unit asymmetry, not a
 *    conversion bug, and the Laravel form labels each field accordingly.
 * 2. *Gate.* OLD then discards the submitted size unless the mirror's
 *    `down` is exactly 1:
 *
 *        if ($Mirror[down] != 1) { $linksize = $Mirror[linksize]; }
 *
 *    The comment beside that line says the opposite intent ("a size cannot
 *    be edited once the item has been fetched into the backup"). Code and
 *    comment disagree. Laravel follows the **code**, because the code is
 *    what the 191 production edits actually did; the contradiction is
 *    raised for an owner decision rather than resolved here by guessing
 *    which one was meant.
 */
final class UpdateAnasheedMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * Whether the size field is honoured for this mirror (see class docblock).
     */
    public static function acceptsSizeEdit(AnasheedMirror $mirror): bool
    {
        return (int) $mirror->down === 1;
    }

    /**
     * @param  array{link?: string|null, title?: string|null, linksize?: mixed, vedio: int|string, backupme?: mixed}  $data
     */
    public function execute(AdminUser $actor, AnasheedMirror $mirror, array $data, ?string $ip = null): AnasheedMirror
    {
        $this->authorizer->authorize($actor, 'anasheed.editmirror', $mirror);

        return DB::connection('main')->transaction(function () use ($actor, $mirror, $data, $ip) {
            /** @var AnasheedMirror $locked */
            $locked = AnasheedMirror::query()->whereKey($mirror->getKey())->lockForUpdate()->firstOrFail();

            $before = $this->snapshot($locked);

            $linksize = self::acceptsSizeEdit($locked)
                ? (int) ($data['linksize'] ?? 0)
                : (int) $locked->linksize;

            $locked->forceFill([
                'title' => (string) ($data['title'] ?? ''),
                'link' => (string) ($data['link'] ?? ''),
                'vedio' => (int) $data['vedio'],
                'backupme' => empty($data['backupme']) ? 0 : 1,
                'linksize' => $linksize,
                'trial' => 0,
            ])->save();

            $after = $this->snapshot($locked);
            $changes = [];

            foreach ($after as $column => $value) {
                if ($before[$column] !== $value) {
                    $changes[$column] = ['from' => $before[$column], 'to' => $value];
                }
            }

            $this->audit->record($actor, 'edit_mirror', 'anasheed', 'mirror', (int) $locked->id, [
                'item_id' => (int) $locked->khid,
                'size_edit_accepted' => self::acceptsSizeEdit($locked),
                'changes' => $changes,
            ], $ip);

            return $locked;
        });
    }

    /** @return array<string, int|string|null> */
    private function snapshot(AnasheedMirror $mirror): array
    {
        return [
            'link' => $mirror->link,
            'title' => $mirror->title,
            'vedio' => (int) $mirror->vedio,
            'backupme' => (int) $mirror->backupme,
            'linksize' => (int) $mirror->linksize,
            'trial' => (int) $mirror->trial,
        ];
    }
}
