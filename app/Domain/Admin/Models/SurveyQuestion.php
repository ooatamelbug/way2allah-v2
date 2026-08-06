<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roadmap task 5.1. `question_options` is a legacy dual-shape column
 * (`add_question.php:44-48`): for `question_type == 7` ("نص قصير" / short
 * text) it stores a raw max-length integer (`$_POST['max_len']`); for
 * every other type it stores a `serialize()`d array of option strings.
 * Reproduced via `optionsArray()`/`maxLength()` rather than a single
 * ambiguous accessor, matching the legacy code's own type-dependent
 * meaning rather than papering over it.
 *
 * `weight` drives display/answer order (`ORDER BY weight ASC, id ASC`
 * throughout the legacy admin pages) — updated via the drag-reorder POST
 * handler (`add_question.php:33-40`), not auto-incrementing.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $des
 * @property string|null $question_options
 * @property int $required
 * @property int $question_type
 * @property int|null $max_sel_num
 * @property int $survey_id
 * @property int $weight
 */
class SurveyQuestion extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_survey_questions';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::created(function (self $question) {
            // add_question.php:59 — UPDATE nuke_survey SET questions = questions + 1.
            Survey::whereKey($question->survey_id)->increment('questions');
        });

        static::deleted(function (self $question) {
            Survey::whereKey($question->survey_id)->decrement('questions');
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    /** The 11 question-type labels, `add_question.php`'s own `<select>` options — used by the admin CRUD UI, not a legacy table. */
    public const QUESTION_TYPES = [
        1 => 'اختيارات متعددة - اجابة واحدة',
        2 => 'اختيارات متعددة اجابات متعددة',
        3 => 'صح و خطأ',
        4 => 'بريد الكتروني',
        5 => 'تاريخ',
        6 => 'قائمة منسدلة',
        7 => 'نص قصير',
        8 => 'نص طويل',
        9 => 'تقييم',
        10 => 'نعم/لا',
        11 => 'تاج',
    ];

    /** Every type except 7 stores a serialized option-string array (`add_question.php:47`). */
    public function optionsArray(): array
    {
        if ((int) $this->question_type === 7 || $this->question_options === null || $this->question_options === '') {
            return [];
        }

        $options = @unserialize($this->question_options);

        return is_array($options) ? $options : [];
    }

    /** Type 7 only — the raw max-length integer (`add_question.php:45`). */
    public function maxLength(): ?int
    {
        return (int) $this->question_type === 7 ? (int) $this->question_options : null;
    }
}
