<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roadmap task 5.1 — its own aggregate (Blueprint §6), referencing `Survey`
 * by id rather than nesting inside it, matching every other independently-
 * accumulating response/comment shape in this migration (`Comment`,
 * `PollVote`). `user_id = 0` means a guest response (`answer.php:48-56`'s
 * own branch, no `users_only` enforcement modeled here — that flag gates
 * the public submission form, out of this Admin-domain-only model's scope
 * per this task's public-UI-absent finding, see `Survey`'s docblock).
 *
 * `answers` is a serialized array keyed by `SurveyQuestion` id
 * (`answer.php:38`, `unserialize($answer->answers)`) — reproduced via
 * `answersArray()` rather than an unserialize-on-every-access pattern
 * spread across controllers/views.
 *
 * @property int $id
 * @property int $survey_id
 * @property int $user_id
 * @property string|null $ip
 * @property int|null $mytime
 * @property string|null $answers
 */
class SurveyAnswer extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_survey_answers';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // `nuke_survey.submits` (confirmed column, `survey/index.php:95`'s
        // own SELECT list) — no increment site exists anywhere in this
        // codebase because the public submission form that would normally
        // own it doesn't exist here (Survey's own docblock: confirmed via
        // exhaustive grep, no `nuke_survey_answers` writer outside
        // `admincp/`). Wired correctly here regardless — an aggregate's
        // counter-cache incrementing on its child's creation is this
        // migration's own established convention (Roadmap task 5.1's
        // "child+counter-cache" note), not new behavior invented for a
        // capability that doesn't exist.
        static::created(function (self $answer) {
            Survey::whereKey($answer->survey_id)->increment('submits');
        });

        static::deleted(function (self $answer) {
            Survey::whereKey($answer->survey_id)->decrement('submits');
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    /** `answer.php:38`/`all_stats.php:51-54` — keyed by SurveyQuestion id. */
    public function answersArray(): array
    {
        if ($this->answers === null || $this->answers === '') {
            return [];
        }

        $answers = @unserialize($this->answers);

        return is_array($answers) ? $answers : [];
    }

    public function isGuest(): bool
    {
        return (int) $this->user_id === 0;
    }
}
