<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OLD Admin Migration Phase 7 — a Mobile Downloads category
 * (`nuke_downloads_categories`). Subcategories are categories whose
 * `parentid` is another category's `cid` — the real two-level hierarchy.
 * (OLD's separate `nuke_downloads_subcategories` table does not exist; the
 * code that used it is dead.)
 *
 * @property int $cid
 * @property string $title
 * @property string $cdescription
 * @property int $parentid
 */
class MobileCategory extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_downloads_categories';

    protected $primaryKey = 'cid';

    public $timestamps = false;

    protected $guarded = ['cid'];

    /** @return BelongsTo<MobileCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parentid');
    }

    /** @return HasMany<MobileCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parentid')->orderBy('title');
    }

    /** @return HasMany<MobileMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(MobileMaterial::class, 'cid');
    }

    /** OLD getparent(): "Parent/Child" — the title with its ancestors. */
    public function path(): string
    {
        $titles = [$this->title];
        $seen = [$this->cid => true];
        $parentId = (int) $this->parentid;
        while ($parentId > 0 && ! isset($seen[$parentId])) {
            $parent = self::query()->find($parentId, ['cid', 'title', 'parentid']);
            if ($parent === null) {
                break;
            }
            $seen[$parentId] = true;
            array_unshift($titles, $parent->title);
            $parentId = (int) $parent->parentid;
        }

        return implode('/', $titles);
    }
}
