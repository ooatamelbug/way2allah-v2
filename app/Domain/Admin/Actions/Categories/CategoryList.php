<?php

namespace App\Domain\Admin\Actions\Categories;

/**
 * OLD Admin Migration Phase 3 — the pipe-delimited category list stored on
 * a content row (`nuke_islamic_khotab.cat`, `nuke_anasheed_anasheed.cat_id`,
 * `nuke_islamic_series.cat`, `nuke_islamic_groups.cat`).
 *
 * **Canonical form is `|3|17|5|`** — a pipe before and after every id. That
 * is the representation every reader (`LIKE '%|id|%'`) and every OLD remover
 * (`str_replace('|id|', '|')`) depends on, and the one the committed OLD
 * code wrote. Production's deployed Cat admin writes `3|17|5|` instead,
 * dropping the leading pipe; the owner classified that as a storage-format
 * regression, not a business rule (plan §20), so it is not reproduced.
 *
 * - **Parsing tolerates both** deployed forms, and any run of empty segments.
 * - **Writing is always canonical**, and happens only for a row that a
 *   Phase 3 operation genuinely changes. Nothing here, and nothing that
 *   calls it, normalizes a row merely because it is in the old form: a
 *   duplicate add or an absent remove leaves the stored string untouched.
 *
 * Tokens are kept as the strings found, in their original order. Only empty
 * segments are dropped — they are representation, not data — so a mutated
 * row keeps every id it already had, exactly. Ids compare as strings, as
 * OLD's own `in_array((string) $id, …, true)` did.
 */
final class CategoryList
{
    /** @return list<string> */
    public static function parse(?string $raw): array
    {
        return array_values(array_filter(
            explode('|', (string) $raw),
            static fn (string $token) => $token !== '',
        ));
    }

    /** @param list<string> $tokens */
    public static function format(array $tokens): string
    {
        // An emptied list is stored as '' rather than OLD's leftover '|':
        // both mean "no categories" to every reader and to the DB triggers.
        return $tokens === [] ? '' : '|'.implode('|', $tokens).'|';
    }

    /** @param list<string> $tokens */
    public static function contains(array $tokens, int $categoryId): bool
    {
        return in_array((string) $categoryId, $tokens, true);
    }

    /**
     * Appends the id unless already present (callers treat that as a no-op
     * before reaching here; this keeps the helper safe on its own).
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    public static function withAdded(array $tokens, int $categoryId): array
    {
        if (! self::contains($tokens, $categoryId)) {
            $tokens[] = (string) $categoryId;
        }

        return $tokens;
    }

    /**
     * Removes every occurrence. OLD's `str_replace('|3|', '|', '|3|3|5|')`
     * left one copy behind because the two matches share a pipe, so the
     * item stayed in the category it was being removed from; removal here
     * means the category is gone.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    public static function withRemoved(array $tokens, int $categoryId): array
    {
        return array_values(array_filter($tokens, static fn (string $t) => $t !== (string) $categoryId));
    }

    /**
     * OLD `cat_repair_series` / `cat_repair_group`: the union of the items'
     * lists, first occurrence wins.
     *
     * @param  iterable<?string>  $rawLists
     * @return list<string>
     */
    public static function union(iterable $rawLists): array
    {
        $union = [];

        foreach ($rawLists as $raw) {
            foreach (self::parse($raw) as $token) {
                $union[$token] = true;
            }
        }

        return array_map('strval', array_keys($union));
    }

    /**
     * Same ids, ignoring order and duplicates. Used so a cascade repair writes
     * a parent only when its content actually changes — never just to
     * re-format a row that already holds the right set.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    public static function sameSet(array $a, array $b): bool
    {
        $a = array_unique($a);
        $b = array_unique($b);
        sort($a, SORT_STRING);
        sort($b, SORT_STRING);

        return $a === $b;
    }
}
