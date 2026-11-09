<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One row per CD group/category (`nuke_w2acd_groups`, `w2acd` module —
 * 00-database-schema.md, Wave 3). Blueprint v1.0 §6: plain Eloquent
 * model, self-referencing hierarchy via `parent_id`.
 *
 * Column list confirmed (Fact): id, title, time, hits, child, w2acd (item
 * count), size, dur, parent_id, author_id, channel_id, des, module_type
 * (default 'w2acd'), icon.
 *
 * `hits` (Fact, already diagnosed by the pre-implementation audit and
 * carried forward as IF-025): legacy's `w2acd/cds.php` has an
 * assignment-in-argument typo that makes every page view increment row
 * `id=0` regardless of the group actually visited — existing `hits`
 * values don't reflect real per-group traffic and should not be trusted
 * if/when migrated as data, per the audit's own migration note. Not
 * reproduced in this port's write path (see `W2acdController`).
 *
 * @property int $id
 * @property string|null $title
 * @property int|null $time
 * @property int $hits
 * @property int|null $child
 * @property int|null $w2acd
 * @property int|null $size
 * @property int|null $dur
 * @property int $parent_id
 * @property int|null $author_id
 * @property int|null $channel_id
 * @property string $des
 * @property string $module_type
 * @property string|null $icon
 */
class W2acdGroup extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_w2acd_groups';

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
        return $this->hasMany(W2acdItem::class, 'group_id');
    }

    /**
     * W2ACD public admin parity, Phase 2 — OLD `Logo()` (`modules/w2acd/index.php`)
     * walks `parent_id` up to the root and prints the chain, this group last.
     * Same shape as `AnasheedGroup::breadcrumbTrail()`; a missing parent ends
     * the walk, and a row seen twice ends it too (OLD looped forever on a cycle).
     *
     * @return Collection<int, self>
     */
    public function breadcrumbTrail(): Collection
    {
        $trail = [$this];
        $seen = [(int) $this->id => true];
        $current = $this;

        while ((int) $current->parent_id > 0 && ! isset($seen[(int) $current->parent_id])) {
            $next = self::find((int) $current->parent_id);
            if ($next === null) {
                break;
            }

            $trail[] = $next;
            $seen[(int) $next->id] = true;
            $current = $next;
        }

        return collect(array_reverse($trail));
    }
}
