<?php

namespace App\Domain\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Roadmap task 3.4 (added post-Wave-4/-5-analysis, retroactively placed
 * in Wave 3 — `docs/reviews/wave-5-gap-reconciliation-proposal.md` item 1).
 * PHP-Nuke's own native poll system (`nuke_poll_desc`/`nuke_poll_data`/
 * `nuke_poll_check`) — Blueprint §6's `Poll`/`PollOption` aggregate.
 * Genuinely unrelated to `App\Domain\Admin\Models\Survey`
 * (`admincp/survey/`'s custom engine, `nuke_survey*` tables, Admin
 * domain) despite the similar name — confirmed two independent systems,
 * `surveys.md`/`admincp.md` cross-referenced.
 *
 * `artid = 0` is the legacy convention for a real homepage/standalone
 * poll (`functions.php:44,98`, `WHERE artid='0'`) — reproduced as a query
 * scope rather than a magic-number filter repeated at every call site.
 *
 * @property int $pollID
 * @property string|null $pollTitle
 * @property int|null $timeStamp
 * @property int $voters
 * @property int $artid
 */
class Poll extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_poll_desc';

    protected $primaryKey = 'pollID';

    public $timestamps = false;

    protected $guarded = ['pollID'];

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class, 'pollID')->orderBy('voteID');
    }

    public function scopeStandalone(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('artid', 0);
    }

    public function totalVotes(): int
    {
        return $this->options->sum('optionCount');
    }
}
