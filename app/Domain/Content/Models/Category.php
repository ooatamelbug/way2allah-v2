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

    /**
     * Batched equivalent of calling `find($id)` then `breadcrumbTrail()` once
     * per id in an ordered list — same output, but the query count scales with
     * the tree's DEPTH rather than with the number of ids.
     *
     * Why this exists: `CategorySeriesController` resolves one trail per
     * pipe-delimited id on `nuke_islamic_series.cat`. Done one id at a time
     * that is `N + ΣDᵢ` queries (production logs showed 201 in a single
     * request, since Eloquent has no identity map and re-fetches every shared
     * ancestor once per sibling). Here each tree LEVEL is one `whereIn`, so a
     * list of 5 ids and a list of 50 ids at the same depth cost the same.
     *
     * `breadcrumbTrail()` above is deliberately left untouched, so its other
     * callers keep their exact current behaviour.
     *
     * Output is occurrence-for-occurrence identical to the per-id version:
     *  - ids are returned in the order given, never database order;
     *  - a repeated id yields a repeated trail (multiplicity is preserved —
     *    ids are deduplicated only for fetching, never in the output);
     *  - an id with no row is silently dropped, as `find()` + `filter()` did;
     *  - each trail contains the leaf itself and runs ancestors-first;
     *  - a trail whose parent row is missing simply ends there.
     *
     * No lazy loading happens during assembly: every model needed is already
     * in `$loaded` before a single trail is built.
     *
     * Cycle protection is defensive only. `breadcrumbTrail()`'s `while` loop
     * would spin forever on a `main_cat` cycle; this resolver cannot, because
     * a level only ever requests ids it has not already loaded, so the
     * frontier empties, and the per-trail walk also stops on a repeated id.
     * Real data is a tree and reaches neither guard.
     *
     * @param  iterable<array-key, int|string>  $ids  ordered, may contain duplicates
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, self>>
     */
    public static function breadcrumbTrailsForIds(iterable $ids): \Illuminate\Support\Collection
    {
        /** @var list<int> $ordered */
        $ordered = [];

        foreach ($ids as $id) {
            $ordered[] = (int) $id;
        }

        if ($ordered === []) {
            return collect();
        }

        /** @var array<int, self> $loaded */
        $loaded = [];

        // One query per tree level. `$pending` only ever holds ids not already
        // loaded, which is what makes a cycle terminate: a row cannot be
        // requested twice, so the frontier empties.
        $pending = array_values(array_unique($ordered));

        while ($pending !== []) {
            /** @var \Illuminate\Support\Collection<int, self> $level */
            $level = static::query()->whereIn('id', $pending)->get();

            if ($level->isEmpty()) {
                break;
            }

            /** @var array<int, int> $next */
            $next = [];

            foreach ($level as $category) {
                $loaded[(int) $category->id] = $category;

                $parentId = (int) $category->main_cat;

                if ($parentId > 0 && ! array_key_exists($parentId, $loaded)) {
                    $next[$parentId] = $parentId;
                }
            }

            $pending = array_values($next);
        }

        /** @var list<\Illuminate\Support\Collection<int, self>> $trails */
        $trails = [];

        foreach ($ordered as $id) {
            if (! array_key_exists($id, $loaded)) {
                // Matches the dropped-null behaviour of find() + filter().
                continue;
            }

            /** @var list<self> $trail */
            $trail = [];
            /** @var array<int, bool> $seen */
            $seen = [];
            $current = $loaded[$id];

            while (true) {
                $currentId = (int) $current->id;

                if (array_key_exists($currentId, $seen)) {
                    // Defensive: a cycle in `main_cat`. Stop rather than spin.
                    break;
                }

                $seen[$currentId] = true;
                $trail[] = $current;

                $parentId = (int) $current->main_cat;

                if ($parentId <= 0 || ! array_key_exists($parentId, $loaded)) {
                    // Root reached, or the parent row does not exist — the same
                    // point at which breadcrumbTrail() breaks.
                    break;
                }

                $current = $loaded[$parentId];
            }

            $trails[] = collect(array_reverse($trail));
        }

        return collect($trails);
    }
}
