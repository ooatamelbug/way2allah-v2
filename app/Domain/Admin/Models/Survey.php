<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Roadmap task 5.1 — `admincp/survey/`'s custom survey engine (Blueprint
 * §4, "the best 1:1 port candidate in the whole audit"). Admin-domain only:
 * this engine has no public voting UI anywhere in this codebase (confirmed
 * via exhaustive grep, `docs/reviews/wave-5-gap-reconciliation-proposal.md`
 * item 1) — not to be confused with `surveys/` (task 3.4, PHP-Nuke's
 * unrelated native `Poll`/`PollOption` system).
 *
 * `editors`/`groups` are pipe-delimited id lists in the legacy column
 * (`add_survey.php:50-60`, `implode('|', ...)`) — reproduced as-is rather
 * than normalized into a pivot table; no evidence any code needs to query
 * "all surveys a given editor moderates," only the reverse (a survey's own
 * form checkbox state), so a pivot table would be unused schema, not a
 * behavior requirement.
 *
 * `questions` is a legacy counter-cache column (`add_question.php:59`,
 * `UPDATE nuke_survey SET questions = questions + 1`) — reproduced via
 * `SurveyQuestion`'s own model events rather than left to controllers to
 * remember, per Roadmap task 5.1's "SurveyAnswer as its own aggregate
 * (child+counter-cache)" note.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $openning
 * @property string|null $finish
 * @property string|null $start_date
 * @property string|null $end_date
 * @property int $users_only
 * @property int $ip_restriction
 * @property int $anonymous
 * @property int $published
 * @property string|null $editors
 * @property string|null $groups
 * @property int $questions
 * @property int $submits
 */
class Survey extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_survey';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function questionsRelation(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id')->orderBy('weight')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'survey_id');
    }

    /** `add_survey.php:50-54`'s pipe-delimited editors list, as an array of vBulletin user ids. */
    public function editorIds(): array
    {
        return $this->editors === null || $this->editors === '' ? [] : explode('|', $this->editors);
    }

    /** `add_survey.php:56-60`'s pipe-delimited groups list, as an array of vBulletin usergroup ids. */
    public function groupIds(): array
    {
        return $this->groups === null || $this->groups === '' ? [] : explode('|', $this->groups);
    }
}
