<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Uploader;
use Illuminate\Support\Facades\DB;

/**
 * `khotab/uploaders.php:19-29`'s `?op=vblink` handler — backfills
 * `uid`/`username` for every uploader row still at `uid=0` by matching
 * email against the vBulletin `user` table. One query per unresolved
 * uploader, matching the legacy loop shape exactly (small, bounded list —
 * not the kind of N+1 worth batching for a small, bounded list).
 */
class BackfillUploaderVbulletinIdentityAction
{
    public function execute(): void
    {
        Uploader::where('uid', 0)->get()->each(function (Uploader $uploader) {
            $vbUser = DB::connection('vbulletin')->table('user')
                ->where('email', $uploader->email)
                ->first(['userid', 'username']);

            if ($vbUser !== null) {
                $uploader->update(['uid' => $vbUser->userid, 'username' => $vbUser->username]);
            }
        });
    }
}
