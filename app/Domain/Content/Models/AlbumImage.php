<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per image within an `Album` (`nuke_albums_images`, `gallery`
 * module — 00-database-schema.md, Wave 3). Blueprint v1.0 §6: `Album`'s
 * one owned child.
 *
 * Column list confirmed (Fact): image_id, album_id, title, url (text,
 * not null), order, hits, author_id, channel_id, creation_date,
 * last_update. `hits`/`author_id`/`channel_id` are confirmed never read
 * or written by the `gallery` module's own code (`gallery.md` §4) —
 * exposed here for completeness, not used by any Wave 4 controller
 * action.
 *
 * `url` is a DB-stored full relative path (e.g.
 * `media/albums/2013/06/1371036756-0nZ52824.jpg`, P-009), not
 * code-constructed — used directly, not bucketed through
 * `MediaPathResolver` (that convention doesn't apply here; this table's
 * own `url` column is already the complete path).
 *
 * @property int $image_id
 * @property int $album_id
 * @property string|null $title
 * @property string $url
 * @property int|null $order
 * @property int|null $hits
 * @property int|null $author_id
 * @property int|null $channel_id
 * @property int|null $creation_date
 * @property int|null $last_update
 */
class AlbumImage extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_albums_images';

    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $guarded = ['image_id'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id', 'album_id');
    }
}
