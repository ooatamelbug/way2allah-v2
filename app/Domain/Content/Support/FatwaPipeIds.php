<?php

namespace App\Domain\Content\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Reading the legacy Fatwa pipe relationships — `nuke_fatwa_general_questions.topic_id`
 * (`|t1|t2|`) and `nuke_fatwa_questions.general_question_id` (`|q1|q2|`) — the
 * way OLD did, now that the Fatwa admin controls (Q4 / Q6, A1 / A3; decision-log
 * #69) create lists with more than one id.
 *
 * - **Membership** (`whereContains`): OLD's `LIKE '%|id|%'`
 *   (`modules/Fatwa/index.php` `questions()`, `all_questions()`,
 *   `all_fatawa_for_auther()`, `get_num_of_fatawa_for_question()`). The id is
 *   an int, so the pattern holds only digits and `|`: no LIKE wildcard, and
 *   because every stored id is wrapped in `|` on both sides it matches the
 *   whole token only — 12 matches `|12|`, `|7|12|45|`, never `|112|` / `|120|`.
 * - **All ids** (`ids`): OLD's `explode('|', …)` over every element
 *   (`all_questions_for_auther()`, `list_Fatawa_for_channel()`).
 * - **One link** (`linkId`): OLD `get_the_first_id()` — the first stored id —
 *   for an element that can link to one question and has no current-question
 *   context.
 * - **One topic** (`firstId`): OLD `get_topic_id_for_general_question()` and
 *   the author / channel lists' `explode('|', $topic_id)[1]` — a general
 *   question's FIRST topic, where a page shows one topic for it (decision-log #71).
 *
 * A value holding at most one id is read exactly as before
 * (`str_replace('|', '', …)`), so every existing single-relationship row keeps
 * its previous result byte for byte; only multi-id values change, and they
 * are never concatenated (`|1|3|` is questions 1 and 3, never 13).
 */
final class FatwaPipeIds
{
    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function whereContains(Builder $query, string $column, int $id): Builder
    {
        return $query->where($column, 'like', '%|'.$id.'|%');
    }

    /** @return list<positive-int> every positive id, in stored order, without repeats */
    public static function ids(mixed $raw): array
    {
        $raw = (string) $raw;
        $tokens = self::tokens($raw);

        if (count($tokens) <= 1) {
            $id = (int) str_replace('|', '', $raw);

            return $id > 0 ? [$id] : [];
        }

        $ids = [];
        foreach ($tokens as $token) {
            $id = (int) $token;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** The first stored id, 0 when there is none (`|258|91|` is 258, never 25891). */
    public static function firstId(mixed $raw): int
    {
        return self::ids($raw)[0] ?? 0;
    }

    /** The single question a contextless link points at: the first stored id. */
    public static function linkId(mixed $raw): string
    {
        $raw = (string) $raw;
        $tokens = self::tokens($raw);

        return count($tokens) <= 1 ? str_replace('|', '', $raw) : $tokens[0];
    }

    /** @return list<string> */
    private static function tokens(string $raw): array
    {
        return array_values(array_filter(explode('|', $raw), fn (string $token) => $token !== ''));
    }
}
