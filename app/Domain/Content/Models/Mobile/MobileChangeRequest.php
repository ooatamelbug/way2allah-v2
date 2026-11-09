<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OLD Admin Migration Phase 7 — `nuke_downloads_modrequest`: one table, two
 * kinds of member request, told apart by `brokendownload`:
 * 1 = broken-link report (only `lid` and the reporter matter);
 * 0 = correction request (the proposed new values of every field).
 *
 * @property int $requestid
 * @property int $lid
 * @property int $cid
 * @property string $title
 * @property string $url
 * @property string $description
 * @property string $modifysubmitter
 * @property int $brokendownload
 * @property string $name
 * @property string $email
 * @property int $filesize
 * @property string $version
 * @property string $homepage
 */
class MobileChangeRequest extends Model
{
    public const BROKEN = 1;

    public const CORRECTION = 0;

    protected $connection = 'main';

    protected $table = 'nuke_downloads_modrequest';

    protected $primaryKey = 'requestid';

    public $timestamps = false;

    protected $guarded = ['requestid'];

    /** @return BelongsTo<MobileMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(MobileMaterial::class, 'lid');
    }
}
