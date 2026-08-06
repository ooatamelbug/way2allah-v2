<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per reciter (Qari)/sub-group in the `telawah` module's
 * hierarchy (`nuke_telawah_groups` — 00-database-schema.md, Wave 3).
 *
 * Column list confirmed (Fact): id, title, time, hits, child, telawah
 * (item count), parent_id, des.
 *
 * `hits` (Fact, already diagnosed by the pre-implementation audit,
 * `telawah.md` §5, carried forward): never incremented by any code in
 * this module — displayed but not live-updated from any confirmed
 * source. Reproduced as found: no increment call anywhere in this
 * port's `TelawahGroupController` either, matching the confirmed absence
 * rather than "fixing" it by adding one legacy never had.
 *
 * @property int $id
 * @property string|null $title
 * @property int|null $time
 * @property int $hits
 * @property int|null $child
 * @property int|null $telawah
 * @property int $parent_id
 * @property string|null $des
 */
class TelawahGroup extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_telawah_groups';

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
        return $this->hasMany(TelawahItem::class, 'group_id');
    }
}
