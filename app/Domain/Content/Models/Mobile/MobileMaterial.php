<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OLD Admin Migration Phase 7 — a Mobile Downloads material
 * (`nuke_downloads_downloads`). `url` is the download target (an external
 * URL — OLD stored no files); `downloadratingsummary` / `totalvotes` /
 * `totalcomments` are the rating counters OLD recomputed from the vote rows.
 *
 * @property int $lid
 * @property int $cid
 * @property int $sid
 * @property string $title
 * @property string $url
 * @property string $description
 * @property string|null $date
 * @property string $name
 * @property string $email
 * @property int $hits
 * @property string $submitter
 * @property float $downloadratingsummary
 * @property int $totalvotes
 * @property int $totalcomments
 * @property int $filesize
 * @property string $version
 * @property string $homepage
 */
class MobileMaterial extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_downloads_downloads';

    protected $primaryKey = 'lid';

    public $timestamps = false;

    protected $guarded = ['lid'];

    /** @return BelongsTo<MobileCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MobileCategory::class, 'cid');
    }

    /** @return HasMany<MobileVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(MobileVote::class, 'ratinglid');
    }

    /** @return HasOne<MobileEditorial, $this> */
    public function editorial(): HasOne
    {
        return $this->hasOne(MobileEditorial::class, 'downloadid');
    }
}
