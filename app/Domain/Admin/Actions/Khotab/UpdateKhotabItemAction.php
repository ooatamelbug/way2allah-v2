<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * «تعديل» on the item page — OLD `KhotabEditKhotab` → `ConfirmEditkhotab`
 * (`modules/Khotab/admin/index.php:1530-1716`, production working copy).
 *
 * OLD's writes, reproduced exactly:
 *
 * - title, description, meta_keywords, meta_description, meta_index,
 *   meta_follow, newslist, fixed, hidden, location_id and notes (`Des`);
 * - `trial = 0`, always;
 * - the link and its size only when a link was posted and the item is not yet
 *   downloaded (`down != 1`) — or the admin is a super-admin (OLD's
 *   `radminsuper`; here the super-admin role). OLD rendered both inputs
 *   `disabled` otherwise, so nothing was posted. With a link, `linksize`
 *   becomes `1024 * 1024 * size`, the size the form shows in MB rounded to two
 *   decimals — as OLD did on every such save;
 * - `channel_id` only for an item in no group and no series (OLD rendered
 *   the channel as plain text for the others);
 * - a changed location: update_author_location, update_series_location,
 *   update_group_location for the new location, then the same three for the
 *   old one.
 *
 * Corrections: authorized (`khotab.edititem`; OLD's `GreenZone('editkhotab')`
 * was inert), one locked transaction, audited with free text digested. OLD's
 * audio/video moderator split (`AvAdmin`) is not reproduced (owner decision,
 * 2026-10-12).
 */
final class UpdateKhotabItemAction
{
    private const TEXT = ['title', 'description', 'meta_keywords', 'meta_description', 'notes', 'link'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
    ) {}

    /** OLD's `$disabled`: may this admin change the link of this item? */
    public static function linkEditable(AdminUser $actor, KhotabItem $item): bool
    {
        return (int) $item->getAttribute('down') !== 1 || $actor->hasRole('super-admin');
    }

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, KhotabItem $item, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.edititem', $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $location = (int) ($data['Location'] ?? 0);
            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'trial' => 0,
                'newslist' => (int) ($data['newslist'] ?? 0) === 1 ? 1 : 0,
                'fixed' => (int) ($data['fixed'] ?? 0) === 1 ? 1 : 0,
                'hidden' => (int) ($data['hidden'] ?? 0) === 1 ? 1 : 0,
                'location_id' => $location,
                'notes' => (string) ($data['Des'] ?? ''),
            ];

            $link = (string) ($data['khotab_link'] ?? '');
            if ($link !== '' && ((int) $row->down !== 1 || $actor->hasRole('super-admin'))) {
                $after['link'] = $link;
                $after['linksize'] = (int) (1024 * 1024 * (float) ($data['khotabsize'] ?? 0));
            }

            if ((int) $row->group_id === 0 && (int) $row->ser_id === 0) {
                $after['channel_id'] = (int) ($data['Channel'] ?? 0);
            }

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update($after);

            $oldLocation = (int) $row->location_id;
            if ($oldLocation !== $location) {
                foreach ([$location, $oldLocation] as $at) {
                    $this->locations->update('author', (int) $row->author, $at);
                    $this->locations->update('series', (int) $row->ser_id, $at);
                    $this->locations->update('group', (int) $row->group_id, $at);
                }
            }

            $this->audit->record($actor, 'edit_item', 'khotab', 'item', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
            ], $ip);
        });
    }
}
