<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Blueprint v1.0 §6: `KhotabItem`'s one owned-child relationship — an
 * alternate download link/quality for the same content (`nuke_islamic_mirror`,
 * 00-database-schema.md).
 *
 * Deliberately does NOT use `TracksViews`/`Viewable`/`ContentViewed`,
 * despite having a `hits` column of the same name as content-item view
 * counters. That pipeline dispatches on a content item's *page view*
 * (Roadmap task 1.4) — a mirror has no page of its own; its `hits` is
 * incremented only when it is *downloaded*
 * (`khotab/functions.php:988`, `download_khotab()`'s
 * `update nuke_islamic_mirror set hits=hits+1 WHERE id=...`), the same
 * event that increments `KhotabItem.downcount` for the primary link. This
 * is a genuinely different concept (download-count, not view-count) that
 * happens to reuse a column named `hits` — reusing the view-tracking
 * abstraction for it would silently conflate the two the first time a
 * page-view listener assumption (e.g. `lastvisit`) got attached to
 * "hits" in general. `incrementDownloadCount()` below is a direct,
 * un-shared atomic increment instead — download-counting recurs only in
 * this one module so far; a shared abstraction is deferred until real
 * evidence from a second module justifies one (same discipline as
 * `VbUserReader`, decision-log.md #2), not built ahead of that evidence.
 *
 * Column list confirmed from 00-database-schema.md's `nuke_islamic_mirror`
 * entry (Fact): id, backup, khid, comment, link, hits, linksize, time,
 * down, trial, cd, backupme, vedio, broken, checktime, online, archgif,
 * thumbnail, hidden, downloader, uploader, addeddate, percent, booking.
 *
 * @property int $id
 * @property int|null $backup
 * @property int $khid
 * @property string|null $comment
 * @property string|null $link
 * @property int $hits
 * @property int|null $linksize
 * @property int|null $time
 * @property int|null $down
 * @property int|null $trial
 * @property int|null $cd
 * @property int|null $backupme
 * @property int|null $vedio
 * @property int|null $broken
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $archgif
 * @property int|null $thumbnail
 * @property int $hidden
 * @property string|null $downloader
 * @property string|null $uploader
 * @property int|null $addeddate
 * @property int|null $percent
 * @property int|null $booking
 */
class Mirror extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_mirror';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function khotabItem(): BelongsTo
    {
        return $this->belongsTo(KhotabItem::class, 'khid');
    }

    public function advanced(): HasOne
    {
        return $this->hasOne(MirrorAdvanced::class, 'id', 'id');
    }

    /** `khotab/functions.php:988` — `update nuke_islamic_mirror set hits=hits+1`. */
    public function incrementDownloadCount(): void
    {
        $this->increment('hits');
    }
}
