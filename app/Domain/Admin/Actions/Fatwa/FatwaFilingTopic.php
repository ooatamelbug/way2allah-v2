<?php

namespace App\Domain\Admin\Actions\Fatwa;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Owner decision 3 (2026-10-30, safe data-integrity correction B):
 *
 *     nuke_fatwa_questions.topic_id
 *       = the FIRST topic id of the fatwa's FIRST general question
 *
 * — OLD's own composition `get_topic_id_for_general_question(get_the_first_id(...))`,
 * which OLD A4 already wrote on every edit and 13,337 / 13,364 local fatwas
 * follow. It is a denormalized filing topic: the category sidebars, the
 * fatwa breadcrumb and `fatwa-today.htm`'s category link read it.
 *
 * OLD left it stale after Q4 / Q5 / Q6 / A1 / A2 / A3 and took Q1's value
 * from the page URL. Laravel derives it here, server-side, from the stored
 * relationships, inside the caller's transaction. A question without a
 * topic files the fatwa under 0, OLD's column default.
 *
 * Existing drifted rows are not swept: only the fatwas an operation touches
 * are recomputed.
 */
final class FatwaFilingTopic
{
    /** The canonical topic id for a fatwa linked to these general questions. */
    public static function forQuestions(PipeIdList $generalQuestions): int
    {
        $first = $generalQuestions->first();
        if ($first === null) {
            return 0;
        }

        return self::primaryTopicOf($first);
    }

    /** The first topic of one general question; 0 when it has none or does not exist. */
    public static function primaryTopicOf(int $generalQuestionId): int
    {
        $topics = self::db()->table('nuke_fatwa_general_questions')->where('id', $generalQuestionId)->value('topic_id');

        return PipeIdList::parse(is_string($topics) ? $topics : null)->first() ?? 0;
    }

    /** Recompute one fatwa from its stored general questions. Returns the stored value. */
    public static function recomputeFatwa(int $fatwaId): int
    {
        $db = self::db();
        $questions = $db->table('nuke_fatwa_questions')->where('id', $fatwaId)->value('general_question_id');
        $topicId = self::forQuestions(PipeIdList::parse(is_string($questions) ? $questions : null));

        $db->table('nuke_fatwa_questions')->where('id', $fatwaId)->update(['topic_id' => $topicId]);

        return $topicId;
    }

    /**
     * Recompute every fatwa whose FIRST general question is this one — the
     * fatwas a change to this question's topics re-files. Fatwas that list
     * it in a later position keep their own primary question and are not
     * touched.
     *
     * @return list<int> the ids of the fatwas whose stored value changed
     */
    public static function recomputeForQuestion(int $generalQuestionId): array
    {
        $db = self::db();
        $topicId = self::primaryTopicOf($generalQuestionId);

        $changed = $db->table('nuke_fatwa_questions')
            ->where(fn ($q) => $q->where('general_question_id', 'like', '|'.$generalQuestionId.'|%')
                ->orWhere('general_question_id', (string) $generalQuestionId))
            ->where(fn ($q) => $q->whereNull('topic_id')->orWhere('topic_id', '!=', $topicId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($changed !== []) {
            $db->table('nuke_fatwa_questions')->whereIn('id', $changed)->update(['topic_id' => $topicId]);
        }

        return $changed;
    }

    private static function db(): ConnectionInterface
    {
        return DB::connection('main');
    }
}
