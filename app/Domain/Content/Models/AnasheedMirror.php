<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An alternate download link/quality for an `AnasheedItem`
 * (`nuke_anasheed_mirror` — 00-database-schema.md, Wave 3) — a
 * properly-normalized child table (unlike `w2acd`'s equivalent, this one
 * IS actively used, per the schema doc's own note).
 *
 * Column list confirmed (Fact): id, backup, khid, title, link, hits,
 * linksize, time, down, trial, cd, backupme, vedio, broken, checktime,
 * online, archgif, thumbnail, hidden, downloader, percent, booking.
 *
 * Deliberately does NOT use `TracksViews`/`Viewable` — same reasoning as
 * `Mirror` (khotab): `hits` here increments on download, not page view.
 *
 * @property int $id
 * @property int|null $backup
 * @property int $khid
 * @property string $title
 * @property string|null $link
 * @property int $hits
 * @property int|null $linksize
 * @property int|null $time
 * @property int|null $down
 * @property int|null $trial
 * @property string $cd
 * @property int|null $backupme
 * @property int|null $vedio
 * @property int|null $broken
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $archgif
 * @property string|null $thumbnail
 * @property int $hidden
 * @property string|null $downloader
 * @property int|null $percent
 * @property int|null $booking
 */
class AnasheedMirror extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_anasheed_mirror';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function anasheedItem(): BelongsTo
    {
        return $this->belongsTo(AnasheedItem::class, 'khid');
    }

    public function advanced(): HasOne
    {
        return $this->hasOne(AnasheedAdvanced::class, 'id', 'id');
    }

    /** `anasheed/functions.php:469` — `update nuke_anasheed_mirror set hits=hits+1`. */
    public function incrementDownloadCount(): void
    {
        $this->increment('hits');
    }
}
