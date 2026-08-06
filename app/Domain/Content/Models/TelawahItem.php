<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Quran recitation — the core content table for the
 * `telawah` module (`nuke_telawah_telawah` — 00-database-schema.md,
 * Wave 3).
 *
 * Column list confirmed (Fact): id, backup, title, notes, link, linksize,
 * banner, hits, downcount, mytime, sorah (Quran surah number), comments,
 * group_id, parent_id, vedio, down, trial, cd, broken, checktime, online,
 * archgif, thumbnail, hidden, fixed, downloader, percent, booking.
 *
 * Deliberately does NOT implement `Viewable`/`TracksViews` — `hits` is
 * only ever displayed, never incremented, by any confirmed code in this
 * module (`telawah/item.php`'s full request flow has no hit-count update
 * anywhere in it — a real, confirmed difference from `KhotabItem`/
 * `AnasheedItem`, not an oversight in this port). Only `downcount` is
 * genuinely written, by `download_telawah()`.
 *
 * No mirrors table exists for this module (confirmed by the absence of
 * any `nuke_telawah_mirror`-shaped table or query anywhere in
 * `telawah/*.php`) — each item has exactly one `link`, unlike
 * `KhotabItem`/`AnasheedItem`.
 *
 * @property int $id
 * @property int|null $backup
 * @property string|null $title
 * @property string|null $notes
 * @property string $link
 * @property int|null $linksize
 * @property string|null $banner
 * @property int $hits
 * @property int $downcount
 * @property int|null $mytime
 * @property int|null $sorah
 * @property int|null $comments
 * @property int $group_id
 * @property int|null $parent_id
 * @property int|null $vedio
 * @property int|null $down
 * @property int|null $trial
 * @property string|null $cd
 * @property int|null $broken
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $archgif
 * @property string|null $thumbnail
 * @property int|null $hidden
 * @property int|null $fixed
 * @property string|null $downloader
 * @property int|null $percent
 * @property int|null $booking
 */
class TelawahItem extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_telawah_telawah';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TelawahGroup::class, 'group_id');
    }

    /** `telawah/functions.php:262` — `update nuke_telawah_telawah set downcount=downcount+1`, the only counter this module actually writes. */
    public function incrementDownloadCount(): void
    {
        $this->increment('downcount');
    }
}
