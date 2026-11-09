<?php

namespace App\Domain\Content\Actions\Mobile;

use App\Domain\Identity\Models\VbUser;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — a member submits a material for review.
 * Replaces OLD `AddDownload` / `Add` (`modules/Mobile/index.php:205-336`):
 *
 * - members only (`$downloads_anonadddownloadlock = 1`; OLD's "member" is the
 *   vBulletin session, `is_user()` = `VB5('id') > 0`);
 * - a URL already used by a published material is refused (OLD
 *   `_DOWNLOADALREADYEXT`);
 * - a submission matching a pending one by title, URL or description is
 *   not stored again, but the member still sees the "received" message —
 *   OLD's silent de-duplication;
 * - otherwise one `nuke_downloads_newdownload` row, `submitter` = the
 *   member's username.
 *
 * Corrected: OLD took `submitter` from the PHP-Nuke cookie, which vBulletin
 * members never had (339 of 341 materials have none); the member's real
 * username is recorded.
 */
final class SubmitMobileMaterialAction
{
    public const RECEIVED = 'received';

    public const URL_EXISTS = 'url_exists';

    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} $data */
    public function execute(VbUser $member, array $data): string
    {
        return DB::connection('main')->transaction(function () use ($member, $data) {
            $main = DB::connection('main');
            if ($main->table('nuke_downloads_downloads')->where('url', $data['url'])->exists()) {
                return self::URL_EXISTS;
            }

            $pending = $main->table('nuke_downloads_newdownload')
                ->where(fn ($q) => $q->where('title', $data['title'])->orWhere('url', $data['url'])->orWhere('description', $data['description']))
                ->lockForUpdate()->exists();

            if (! $pending) {
                $main->table('nuke_downloads_newdownload')->insert($data + ['sid' => 0, 'submitter' => (string) $member->username]);
            }

            return self::RECEIVED;
        });
    }
}
