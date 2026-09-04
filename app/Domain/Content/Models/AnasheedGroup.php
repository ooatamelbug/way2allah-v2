<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One row per group/sub-group in the `anasheed` module's hierarchy
 * (`nuke_anasheed_groups` — 00-database-schema.md, Wave 3). Structurally
 * near-identical to `W2acdGroup`/`TelawahGroup` (schema doc's own
 * migration note flags this as a candidate for a shared query-scope-level
 * abstraction, not a literal shared table) — kept as its own model per
 * this project's standing discipline of waiting for a second real
 * consumer's evidence before extracting a shared abstraction (same
 * reasoning as `KhotabItem`/`Mirror`'s deliberately un-shared
 * `incrementDownloadCount()`), not because no similarity was noticed.
 *
 * Column list confirmed (Fact): id, title, time, hits, child, anasheed
 * (item count), size, dur, parent_id, channel_id, author_id, des,
 * description, meta_keywords, meta_description, meta_index, meta_follow,
 * module_type (default 'anasheed'), icon.
 *
 * @property int $id
 * @property string|null $title
 * @property int|null $time
 * @property int $hits
 * @property int|null $child
 * @property int|null $anasheed
 * @property int|null $size
 * @property int|null $dur
 * @property int $parent_id
 * @property int|null $channel_id
 * @property int|null $author_id
 * @property string|null $des
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property string $module_type
 * @property int|null $icon
 */
class AnasheedGroup extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_anasheed_groups';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnasheedItem::class, 'group_id');
    }

    /**
     * G-13-04 (media/visual parity phase) — `anasheed/functions.php:208-212`'s
     * sub-group listing icon: `icon==1` selects the bucketed
     * `media/anasheed/icons/{bucket}/{id}.jpg` (no `file_exists()` gate,
     * unconditional on the flag alone), else a shared static fallback,
     * `images/pix001.gif` — distinct from `tvnoise.gif`/`way2_withoutimg.png`,
     * confirmed by direct reading, not assumed to be the same placeholder.
     */
    public function thumbUrl(): string
    {
        if ($this->icon === 1) {
            return MediaUrl::asset(MediaPathResolver::path('anasheed/icons', $this->id, 'jpg'));
        }

        return '/images/pix001.gif';
    }

    /**
     * var-item-17350.htm parity: `anasheed/item.php:47-58`'s breadcrumb
     * ancestor walk — `while ($Group->parent_id > 0) { fetch parent }`,
     * then `array_reverse()`, ending with this group last (the caller
     * appends the item title after this). Same shape as `Category::
     * breadcrumbTrail()` (`main_cat` there vs `parent_id` here) — a
     * page-local model helper, not a new shared hierarchy abstraction.
     */
    public function breadcrumbTrail(): Collection
    {
        /** @var list<self> $trail */
        $trail = [$this];
        $current = $this;

        while ($current->parent_id > 0) {
            $next = self::find($current->parent_id);

            if ($next === null) {
                break;
            }

            $trail[] = $next;
            $current = $next;
        }

        return collect(array_reverse($trail));
    }
}
