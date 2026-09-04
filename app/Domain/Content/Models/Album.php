<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per image gallery album (`nuke_albums`, `gallery` module —
 * 00-database-schema.md, Wave 3). Blueprint v1.0 §6: an aggregate root
 * with one owned child, `AlbumImage` (`Album`/`CdItem`/`AnasheedItem`/
 * `Recitation` row in §6's aggregate table).
 *
 * Column list confirmed (Fact): album_id, title, des, order, count, hits,
 * is_compressed, author_id, channel_id, creation_date, last_update.
 *
 * IF-027's fix: `order` is a real stored column but never actually drove
 * display order in legacy (`get_albums()`'s `@order`-based `ORDER BY`
 * never resolves — the session variable is never set). `orderedForListing()`
 * below orders by `album_id` instead — the closest honest reproduction of
 * "no effective sort was ever actually applied," not a guess at what the
 * `order` column was originally intended to do.
 *
 * @property int $album_id
 * @property string|null $title
 * @property string|null $des
 * @property int|null $order
 * @property int $count
 * @property int $hits
 * @property int $is_compressed
 * @property int|null $author_id
 * @property int|null $channel_id
 * @property int|null $creation_date
 * @property int|null $last_update
 */
class Album extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_albums';

    protected $primaryKey = 'album_id';

    public $timestamps = false;

    protected $guarded = ['album_id'];

    public function images(): HasMany
    {
        return $this->hasMany(AlbumImage::class, 'album_id', 'album_id');
    }

    /** `gallery/functions.php:18-23`'s `album_thumb()` — first image by `order ASC`. */
    public function thumbnailImage(): ?AlbumImage
    {
        return AlbumImage::where('album_id', $this->album_id)->orderBy('order')->first();
    }

    /**
     * The same "first image by `order ASC`" rule as `thumbnailImage()`
     * above, resolved for many albums in one query instead of one query
     * per album — the gallery index rendered 84 albums as 1 + 84 queries.
     *
     * Deliberately not an `ofMany()` relationship: that builds a
     * MIN(`order`) subquery which, on two images sharing an album's
     * lowest `order`, disambiguates by primary key. `thumbnailImage()`
     * has no tiebreaker at all — it takes whatever `ORDER BY `order``
     * yields first — so adding one here would be a silent behaviour
     * change. Ordering the rows exactly as the per-album query does and
     * keeping the first per album reproduces the current winner,
     * including that undefined-on-ties behaviour.
     *
     * Returns urls rather than models because that is all the listing
     * uses, and hydrating 2,522 `AlbumImage` models to read 84 of them
     * would trade one inefficiency for another.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $albums
     * @return array<int, string>  album_id => thumbnail url (absent when the album has no images)
     */
    public static function thumbnailUrlsFor(\Illuminate\Support\Collection $albums): array
    {
        $albumIds = $albums->pluck('album_id')->all();

        if ($albumIds === []) {
            return [];
        }

        $urls = [];

        foreach (
            AlbumImage::query()
                ->whereIn('album_id', $albumIds)
                ->orderBy('album_id')
                ->orderBy('order')
                ->toBase()
                ->get(['album_id', 'url']) as $image
        ) {
            $urls[(int) $image->album_id] ??= $image->url;
        }

        return $urls;
    }

    /** `nuke_albums` has no `lastvisit` column at all. */
    public function tracksLastVisit(): bool
    {
        return false;
    }
}
