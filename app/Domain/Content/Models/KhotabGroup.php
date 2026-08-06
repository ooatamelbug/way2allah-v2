<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named collection of khotab items, one level between `Author` and
 * `Series`/`KhotabItem` (`khotab/group.php`, `nuke_islamic_groups`) —
 * decision-log.md #6: added as a plain, referenced Eloquent model per
 * Blueprint §6's existing rule for entities the frozen ER diagram didn't
 * enumerate (a documentation gap, not a contradiction). Named `KhotabGroup`
 * rather than `Group` to avoid colliding with the unrelated permission
 * concept (`App\Support\Permission\Role`) and with `w2acd`/`anasheed`
 * modules' own, separate `*_groups` tables should those be modeled later.
 *
 * Some series/items skip this level entirely (`group_id = 0` — confirmed,
 * `khotab/functions.php`'s `ListSeries()`/`ListKhotab()` both branch on
 * `group_id > 0` as an optional case), so this is a referenced, optional
 * grouping, not a mandatory hierarchy level.
 *
 * Column list confirmed from 00-database-schema.md's `nuke_islamic_groups`
 * entry (Fact): id, title, description, meta_keywords, meta_description,
 * meta_index, meta_follow, time, count, size, dur, author_id, channel_id,
 * des, cat, vedio, banner, advfile, hidden, lastupdate.
 *
 * `cat` (pipe-delimited category list) shares the same data-quality
 * anti-pattern already fixed for `nuke_islamic_khotab.cat` via
 * `khotab_category_index` — NOT yet fixed for this table (00-database-
 * schema.md, confirmed still open). Deliberately not exposed as a
 * relationship here; categories/functions.php's `groupsByCategory()`
 * equivalent (already built in ContentListingService) queries it as a raw
 * `LIKE` filter, matching legacy exactly, not a junction-table relationship
 * this model doesn't actually have.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property int|null $time
 * @property int $count
 * @property int|null $size
 * @property int|null $dur
 * @property int $author_id
 * @property int|null $channel_id
 * @property string|null $des
 * @property string|null $cat
 * @property int $vedio
 * @property int|null $banner
 * @property int|null $advfile
 * @property int $hidden
 * @property int|null $lastupdate
 */
class KhotabGroup extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_groups';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class, 'group_id');
    }

    public function khotabItems(): HasMany
    {
        return $this->hasMany(KhotabItem::class, 'group_id');
    }
}
