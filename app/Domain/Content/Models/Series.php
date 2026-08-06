<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named multi-part lecture series (`khotab/series.php`, `nuke_islamic_series`)
 * — Blueprint v1.0 §6: no owned children of its own, references `Author`
 * and `Channel` only. `KhotabItem`s reference a series by `ser_id`, not the
 * other way around (§6's "referenced, not owned" direction).
 *
 * Column list confirmed from 00-database-schema.md's `nuke_islamic_series`
 * entry (Fact): id, old_id, title, description, meta_keywords,
 * meta_description, meta_index, meta_follow, folder, time, count, size,
 * dur, parent_id, author_id, channel_id, des, cat, group_id, vedio, banner,
 * advfile, hidden, lastupdate, ramadan.
 *
 * `parent_id` (self-referential, "series of series"?) is Confidence: Low/
 * Hypothesis in 00-database-schema.md — no usage observed anywhere in the
 * audit. Deliberately NOT exposed as a relationship here; adding one would
 * be exactly the kind of unverifiable scaffolding the Wave 0 review found
 * none of. Revisit only if real evidence of its use turns up.
 *
 * @property int $id
 * @property int|null $old_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property string|null $folder
 * @property int|null $time
 * @property int $count
 * @property int|null $size
 * @property int|null $dur
 * @property int|null $parent_id
 * @property int $author_id
 * @property int|null $channel_id
 * @property string|null $des
 * @property string|null $cat
 * @property int $group_id
 * @property int $vedio
 * @property int|null $banner
 * @property int|null $advfile
 * @property int $hidden
 * @property int|null $lastupdate
 * @property int|null $ramadan
 */
class Series extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_series';

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(KhotabGroup::class, 'group_id');
    }

    /** `series_category_index` junction table (Fact, `db_migration.sql`) — unlike `nuke_islamic_groups.cat`, series' category linkage was already fixed off the pipe-delimited `cat` column pattern. */
    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'series_category_index', 'series_id', 'category_id');
    }

    public function khotabItems(): HasMany
    {
        return $this->hasMany(KhotabItem::class, 'ser_id');
    }
}
