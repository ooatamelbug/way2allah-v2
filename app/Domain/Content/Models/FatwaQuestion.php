<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scholar's specific answer to a `FatwaGeneralQuestion`
 * (`nuke_fatwa_questions`) — Roadmap task 6.1. Linked to its general
 * question via the pipe-delimited `general_question_id` column
 * (`'|123|'`), the same convention `topic_id` uses on both this table and
 * `FatwaGeneralQuestion` — **preserved as-is (Option A), not normalized
 * into a junction table.** See `ContentListingService::fatwaQuestionsForGeneralQuestion()`
 * for the actual lookup (not an Eloquent relation here, for the same
 * reason documented on `FatwaGeneralQuestion`).
 *
 * `auther_id` is the legacy column's own spelling (confirmed, not a typo
 * introduced here) — the scholar who gave this specific answer, distinct
 * from `FatwaGeneralQuestion.author_id`'s uncertain meaning (never
 * disambiguated in the legacy source read for this module; not asserted
 * here).
 *
 * Column list confirmed from `fatawa.md` §4 (Fact, 15 columns): id,
 * topic_id, general_question_id, question_text, auther_id, channel_id,
 * answer_text, media_type, media_link, media_size, place_of_fatwa,
 * date_of_fatwa, db_insertion_date, num_view, num_download.
 *
 * View/download counters: `answer.php`/`answer2.php` increment this
 * row's *general question*'s `num_view` (see `FatwaGeneralQuestion`), not
 * a column on this table — `num_view`/`num_download` here are confirmed
 * to exist in the schema but no call site incrementing them was found in
 * the 16 files read for this module; not wired to any counter mechanism
 * in this increment, to avoid inventing behavior not evidenced.
 *
 * @property int $id
 * @property string|null $topic_id
 * @property string|null $general_question_id
 * @property string|null $question_text
 * @property int|null $auther_id
 * @property int|null $channel_id
 * @property string|null $answer_text
 * @property string|null $media_type
 * @property string|null $media_link
 * @property int|null $media_size
 * @property string|null $place_of_fatwa
 * @property string|null $date_of_fatwa
 * @property int|null $db_insertion_date
 * @property int|null $num_view
 * @property int|null $num_download
 * @property-read \App\Domain\Content\Models\Author|null $author
 * @property-read \App\Domain\Content\Models\Channel|null $channel
 */
class FatwaQuestion extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_fatwa_questions';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'auther_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
