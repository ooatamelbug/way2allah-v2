<?php

namespace App\Domain\Admin\Actions\Mirrors;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Anasheed mirror insert. Replaces OLD `ConfirmInsertMirror`
 * (`modules/Anasheed/admin/index.php:77-215`).
 *
 * Same shape as Khotab's insert with the two real differences (3E.1 §3.2):
 * the label column is `title`, not `comment`; and **there is no duplicate
 * guard** — OLD's Anasheed insert only checked that the parent existed.
 * Production holds 21 per-parent duplicate groups as a direct consequence,
 * and adding a guard now would be new rejection, not parity (3E.1 C12).
 *
 * Quality/backup columns are left to their table defaults, as for Khotab.
 */
final class InsertAnasheedMirrorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{link: string, title?: string|null, linksize_mb?: mixed, vedio: int|string, backupme?: mixed}  $data
     */
    public function execute(AdminUser $actor, AnasheedItem $item, array $data, ?string $ip = null): AnasheedMirror
    {
        $this->authorizer->authorize($actor, 'anasheed.addmirror', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $data, $ip) {
            $time = time();

            $mirror = new AnasheedMirror;
            $mirror->forceFill([
                'khid' => (int) $item->id,
                'link' => (string) $data['link'],
                'title' => (string) ($data['title'] ?? ''),
                'linksize' => MirrorParentCounter::megabytesToBytes($data['linksize_mb'] ?? 0),
                'vedio' => (int) $data['vedio'],
                'backupme' => empty($data['backupme']) ? 0 : 1,
                'time' => $time,
            ])->save();

            MirrorParentCounter::increment('nuke_anasheed_anasheed', (int) $item->id, $time);

            // OLD logged Anasheed against the parent only (`type='item'`),
            // so its trail could never answer "what happened to this
            // mirror". Laravel applies Khotab's dual-row convention to both
            // domains (3E.2 §8.2.2).
            $this->audit->record($actor, 'add_mirror', 'anasheed', 'mirror', (int) $mirror->id, [
                'item_id' => (int) $item->id,
                'link' => $mirror->link,
                'title' => $mirror->title,
                'linksize' => (int) $mirror->linksize,
                'vedio' => (int) $mirror->vedio,
                'backupme' => (int) $mirror->backupme,
            ], $ip);

            $this->audit->record($actor, 'add_mirror', 'anasheed', 'item', (int) $item->id, [
                'mirror_id' => (int) $mirror->id,
            ], $ip);

            return $mirror;
        });
    }
}
