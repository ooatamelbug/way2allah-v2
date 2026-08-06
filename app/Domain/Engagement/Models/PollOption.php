<?php

namespace App\Domain\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roadmap task 3.4. `nuke_poll_data` — no auto-increment primary key
 * (`surveys.md` §4, "composite key implied by pollID+voteID"); `voteID`
 * runs 1-12 for real, displayed options (`functions.php:15,157`'s own
 * `for ($i = 1; $i <= 12; $i++)` display loops) — reproduced with that
 * same 1-12 range consistently for both display and vote-counting,
 * rather than also reproducing the legacy sum loop's own inconsistent
 * 0-11 range (`functions.php:29,124,150`), since nothing anywhere ever
 * writes a `voteID=0` row — the two ranges are only cosmetically
 * different, not behaviorally, given that.
 *
 * @property int $pollID
 * @property int $voteID
 * @property string|null $optionText
 * @property int $optionCount
 */
class PollOption extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_poll_data';

    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = [];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'pollID');
    }
}
