<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * «سحب الصور» on the item page — OLD `get_gif_jpg.php?id=` (the site root,
 * opened in a popup), for one item.
 *
 * OLD's intended effect, preserved: for an item whose link is on
 * archive.org, read the item's archive.org file list, store its animated GIF
 * as `media/khotab_gifs/{id/1000}/{id}.gif` (`gif = 1`) and its middle
 * thumbnail as `media/khotab_frames/{id/1000}/{id}.jpg` (`frame = 1`), then
 * mark the item `leeched = 1` — also when archive.org offered neither image.
 * An item whose link is not on archive.org is left untouched, as OLD's
 * `link LIKE '%archive.org%'` query left it.
 *
 * Not reproduced (owner decision, 2026-10-21): OLD's script had no
 * authentication, ran on a GET, wrote to a hard-coded server path, appended
 * to an existing image instead of replacing it, and no longer ran on PHP 8.
 * Here it is a CSRF-protected POST for `khotab.edititem`, reading through the
 * existing ArchiveOrgMetadata reader and storing through KhotabArchiveImages
 * (archive.org URLs only, every request bounded by
 * `admin_khotab.archive_timeout`). The network work happens before the
 * database writes, which are one transaction with the audit row.
 */
final class LeechKhotabItemImagesAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly ArchiveOrgMetadata $archive,
        private readonly KhotabArchiveImages $images,
    ) {}

    /** @return array{archive: bool, gif: bool, frame: bool} */
    public function execute(AdminUser $actor, KhotabItem $item, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.edititem', $item);

        $link = (string) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item->id)->value('link');
        if (stripos($link, 'archive.org') === false) {
            return ['archive' => false, 'gif' => false, 'frame' => false];
        }

        $metadata = $this->archive->read($link);
        $flags = $this->images->store((int) $item->id, $metadata['gif'], $metadata['frame']);

        DB::connection('main')->transaction(function () use ($actor, $item, $flags, $metadata, $ip) {
            $main = DB::connection('main');
            abort_unless($main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()->exists(), 404);

            $main->table('nuke_islamic_khotab')->where('id', $item->id)->update($flags + ['leeched' => 1]);

            $this->audit->record($actor, 'leech_images', 'khotab', 'item', (int) $item->id, [
                'metadata_found' => $metadata['found'],
                'gif' => isset($flags['gif']),
                'frame' => isset($flags['frame']),
            ], $ip);
        });

        return ['archive' => true, 'gif' => isset($flags['gif']), 'frame' => isset($flags['frame'])];
    }
}
