<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The topic-based category tree (`nuke_w2a_cat`), independent of the
 * author/series/group axis `Author`/`Series`/`KhotabGroup` use
 * (00-database-schema.md's `nuke_w2a_cat` entry, Wave 2). Blueprint v1.0
 * §6: plain Eloquent model, referenced many-to-many by `KhotabItem`
 * (via `khotab_category_index`) and `Series` (via `series_category_index`),
 * self-referential parent-of tree via `main_cat`.
 *
 * Column list confirmed (Fact): id, title, description, meta_keywords,
 * meta_description, meta_index, meta_follow, main_cat, level, oldid,
 * q_count, serious_count, audio, audio_count, video, video_count,
 * anasheed_count, recite, lastupdate.
 *
 * `main_cat` (Fact, `categories/functions.php:502`'s `Cat_Breadcrumb()`
 * — `while ($Cat->main_cat > 0) { ... }`) is the parent-category id; `0`
 * (not null) means "top-level, no parent" — confirmed by the `> 0` loop
 * condition, not an assumption.
 *
 * `*_count` columns (video_count/audio_count/anasheed_count/q_count) are
 * denormalized counters — 00-database-schema.md flags them as "not
 * confirmed whether kept in sync by triggers or application code," so
 * they're exposed here as plain columns, not trusted as a computed
 * relationship count.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property int $main_cat
 * @property int|null $level
 * @property int|null $oldid
 * @property int|null $q_count
 * @property int|null $serious_count
 * @property int|null $audio
 * @property int|null $audio_count
 * @property int|null $video
 * @property int|null $video_count
 * @property int|null $anasheed_count
 * @property int|null $recite
 * @property int|null $lastupdate
 */
class Category extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_w2a_cat';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'main_cat');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'main_cat');
    }

    public function khotabItems(): BelongsToMany
    {
        return $this->belongsToMany(KhotabItem::class, 'khotab_category_index', 'category_id', 'khotab_id');
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'series_category_index', 'category_id', 'series_id');
    }

    /**
     * `categories/functions.php:493-511`'s `Cat_Breadcrumb()` — walks
     * `main_cat` up to the root, returning ancestors-first (this category
     * last), matching legacy's own `array_reverse($breadcrumb)`. Returns
     * `Category` models, not the breadcrumb array's title/url shape —
     * that's a view-layer concern for whichever Wave 4 category-browsing
     * controller consumes this, not this model's job.
     */
    public function breadcrumbTrail(): \Illuminate\Support\Collection
    {
        /** @var list<self> $trail */
        $trail = [$this];
        $current = $this;

        while ($current->main_cat > 0) {
            $next = self::find($current->main_cat);

            if ($next === null) {
                break;
            }

            $trail[] = $next;
            $current = $next;
        }

        return collect(array_reverse($trail));
    }
}
