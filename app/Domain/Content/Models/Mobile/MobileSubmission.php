<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;

/**
 * OLD Admin Migration Phase 7 — a member's material submission waiting for
 * staff review (`nuke_downloads_newdownload`).
 *
 * @property int $lid
 * @property int $cid
 * @property string $title
 * @property string $url
 * @property string $description
 * @property string $name
 * @property string $email
 * @property string $submitter
 * @property int $filesize
 * @property string $version
 * @property string $homepage
 */
class MobileSubmission extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_downloads_newdownload';

    protected $primaryKey = 'lid';

    public $timestamps = false;

    protected $guarded = ['lid'];
}
