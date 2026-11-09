<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One alternative link of a Telawah recitation — `nuke_telawah_mirror`
 * (OLD Admin Migration, closure correction, 2026-10-15).
 *
 * Production shape (Pass 3E.1 §0.2.1): `id`, `telawa_id`, `comment`,
 * `link` VARCHAR(255), `extension`, `size`, `video` CHAR, `backupme` CHAR,
 * `creation_date`. Unlike the Khotab and Anasheed mirror tables it has no
 * `khid`, no backup-queue columns (`down`/`trial`/`booking`) and no
 * link-quality columns, and the parent has no `mirror`/`lastmirror`
 * counters — so nothing here participates in the backup or link-quality
 * programmes.
 *
 * @property int $id
 * @property int|null $telawa_id
 * @property string $comment
 * @property string|null $link
 * @property string|null $extension
 * @property int|null $size
 * @property string|null $video
 * @property string $backupme
 * @property string|null $creation_date
 */
class TelawahMirror extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_telawah_mirror';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<TelawahItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(TelawahItem::class, 'telawa_id');
    }
}
