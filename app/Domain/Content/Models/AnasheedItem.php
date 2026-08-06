<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per item (nasheed/documentary/misc media) — the core content
 * table for the `anasheed` module (`nuke_anasheed_anasheed` —
 * 00-database-schema.md, Wave 3, 43 columns, closely mirroring
 * `nuke_islamic_khotab`'s shape).
 *
 * Column list confirmed (Fact, 43 total): id, converted, backup, title,
 * notes, description, meta_keywords, meta_description, meta_index,
 * meta_follow, link, linksize, frame, anasheed_banner, hits, downcount,
 * mytime, weight, comments, group_id, parent_id, channel_id, author_id,
 * cat_id, down, trial, cd, broken, vedio, mirror, lastmirror, checktime,
 * online, fixed, archgif, thumbnail, hidden, lastvisit, lastupdate,
 * order_in_group, downloader, percent, booking.
 *
 * `hidden` (Fact, confirmed by direct reading of `anasheed/item.php` —
 * already flagged by the pre-implementation audit, `anasheed.md` §5,
 * carried forward as IF-028) is NOT filtered anywhere in this module's
 * queries — a genuine behavioral gap versus `KhotabItem`'s enforcement of
 * the same column, reproduced as found (needs a product-owner
 * confirmation before "fixing," per the audit's own note — unlike
 * IF-014-style unambiguous typos).
 *
 * `link` is a full external/absolute URL, NOT a local filesystem path
 * (confirmed by `download_anasheed()`'s `Header("Location: " . $_link)`
 * redirect — never `fopen()`s it) — a real, confirmed difference from
 * `KhotabItem::$link`, which IS a local path streamed via `fopen()`. Do
 * NOT reuse `KhotabItemController::download()`'s streaming approach here.
 *
 * @property int $id
 * @property int|null $converted
 * @property int|null $backup
 * @property string|null $title
 * @property string|null $notes
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property string $link
 * @property int|null $linksize
 * @property int $frame
 * @property int|null $anasheed_banner
 * @property int $hits
 * @property int $downcount
 * @property int|null $mytime
 * @property int|null $weight
 * @property int|null $comments
 * @property int $group_id
 * @property int|null $parent_id
 * @property int|null $channel_id
 * @property int|null $author_id
 * @property string|null $cat_id
 * @property int|null $down
 * @property int|null $trial
 * @property string|null $cd
 * @property int|null $broken
 * @property int|null $vedio
 * @property int $mirror
 * @property int|null $lastmirror
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $fixed
 * @property int|null $archgif
 * @property string|null $thumbnail
 * @property int|null $hidden
 * @property int|null $lastvisit
 * @property int|null $lastupdate
 * @property int|null $order_in_group
 * @property string|null $downloader
 * @property int|null $percent
 * @property int|null $booking
 */
class AnasheedItem extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_anasheed_anasheed';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AnasheedGroup::class, 'group_id');
    }

    public function mirrors(): HasMany
    {
        return $this->hasMany(AnasheedMirror::class, 'khid');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnasheedComment::class, 'khid');
    }

    /** `anasheed/functions.php:462` — `update nuke_anasheed_anasheed set downcount=downcount+1`, same distinct-from-view-tracking reasoning as `KhotabItem::incrementDownloadCount()`. */
    public function incrementDownloadCount(): void
    {
        $this->increment('downcount');
    }

    /**
     * `anasheed/functions.php:312`'s `list_anasheed()` group filter,
     * including its group-98 special case — extracted here (Wave 5) once
     * a second real consumer needed the identical filter
     * (`AnasheedGroupController`, `AnasheedNewsController`), rather than
     * duplicating the 3-line conditional a second time. IF-029 confirms
     * why group 98 is special: it's `anasheed-news.htm`'s themed
     * aggregation target, and `OR group_id='16'` is that theme's own
     * business rule.
     */
    public function scopeInGroup(\Illuminate\Database\Eloquent\Builder $query, int $groupId): void
    {
        if ($groupId === 98) {
            $query->whereIn('group_id', [98, 16]);
        } else {
            $query->where('group_id', $groupId);
        }
    }
}
