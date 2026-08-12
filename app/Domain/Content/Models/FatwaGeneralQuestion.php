<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * A "general question" (`nuke_fatwa_general_questions`) — one question that
 * may have one or more scholar answers (`FatwaQuestion`, joined via the
 * pipe-delimited `general_question_id` convention). Roadmap task 6.1.
 *
 * **Deliberately no `answers(): HasMany` relation on this model** — the
 * join key is a pipe-delimited string (`'|123|'`), not a plain equality
 * match, so a standard Eloquent `hasMany()` would silently generate a
 * `WHERE general_question_id = 123` query that never matches anything.
 * The pipe-aware lookup lives in `ContentListingService::fatwaQuestionsForGeneralQuestion()`
 * instead, matching this project's established convention for this exact
 * pattern (`ContentListingService::groupsByCategory()`'s `LIKE '%|id|%'`
 * query against `nuke_islamic_groups.cat`).
 *
 * `topic_id` is a pipe-delimited multi-membership string
 * (`fatawa/functions.php:195-202`'s `question_has_many_topics()`/
 * `get_the_first_id()`) — the same confirmed anti-pattern `nuke_islamic_khotab.cat`
 * had before `khotab_category_index` normalized it, except this table
 * never received that fix (`fatawa.md` §4/§8). **Preserved exactly as-is
 * (Option A, per the approved technical plan) — queried via `LIKE`
 * against the raw pipe-delimited string, not a junction table.** No
 * schema change is made here.
 *
 * Column list confirmed from `fatawa.md` §4 (Fact): id, question_text,
 * description, meta_keywords, meta_description, meta_index, meta_follow,
 * topic_id, num_view, author_id, channel_id.
 *
 * View counter: legacy's `answer.php`/`answer2.php` increment `num_view`
 * via a non-atomic read-then-write (`fatawa.md` §5) — modernized here to
 * `RecordsView`'s atomic increment, matching this project's established
 * precedent (decision-log #9) that atomicity is not an observable
 * behavior change. `single.php` never increments this column at all
 * (confirmed asymmetry, Legacy Evidence Verification round 1) — preserved
 * by simply not calling `recordView()` from the `single`-equivalent
 * action.
 *
 * @property int $id
 * @property string|null $question_text
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property string|null $topic_id
 * @property int $num_view
 * @property int|null $author_id
 * @property int|null $channel_id
 */
class FatwaGeneralQuestion extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_fatwa_general_questions';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** `nuke_fatwa_general_questions` has no `lastvisit` column (confirmed absent from `fatawa.md` §4's column list). */
    public function tracksLastVisit(): bool
    {
        return false;
    }

    public function viewCountColumn(): string
    {
        return 'num_view';
    }
}
