<?php

namespace App\Domain\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OLD Admin Migration Phase 4. `nuke_pollcomments` — PHP-Nuke's threaded
 * poll comments: `pid` is the parent comment's `tid` (0 for a top-level
 * comment). Laravel renders no public comment pages (see
 * `routes/engagement.php`); this model exists for poll-comment moderation
 * only.
 *
 * @property int $tid
 * @property int $pid
 * @property int $pollID
 * @property string|null $date
 * @property string $name
 * @property string $subject
 * @property string|null $comment
 */
class PollComment extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_pollcomments';

    protected $primaryKey = 'tid';

    public $timestamps = false;

    protected $guarded = ['tid'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'pollID');
    }
}
