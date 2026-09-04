<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;

/**
 * Decides whether one of the performance-index migrations (E-04, E-05,
 * E-08, E-12) still has work to do against whatever index set the target
 * database actually has.
 *
 * **Why this exists.** Those migrations originally guarded themselves with
 * a name check alone — `SHOW INDEX … WHERE Key_name = ?`. That is correct
 * only when a hand-applied index carries the *same name* the migration
 * would have used, which is the case we tested during the cPanel
 * phpMyAdmin-first deployments. Production turned out to present the other
 * case: it already carried structurally equivalent indexes under different
 * names, added outside this programme —
 *
 *     migration wants        production already has
 *     (khid)                 idx_khid          (khid, id)
 *     (pdf)                  idx_pdf_time      (pdf, pdf_time)
 *     (vedio, time)          idx_vedio_time    (vedio, time)
 *     (location_id)          idx_location_author_hidden (location_id, author, hidden)
 *
 * A name-only guard finds none of those, so `migrate` would have created
 * four redundant indexes. MySQL permits duplicate indexes with a warning
 * rather than an error, so it would have looked like success while costing
 * ~20 MB, extra write amplification, and handing the optimizer more
 * equivalent candidates on a table that has already shown it makes
 * surprising choices.
 *
 * **The rule.** A desired index is already covered when its ordered column
 * list is an exact left-prefix of some existing index on the same table.
 * That is a sound equivalence, not a heuristic: a longer index beginning
 * with the same columns can serve everything the shorter one can — both
 * narrowing on those columns and ordering by them.
 *
 * Deliberately used by `up()` only. `down()` must stay name-based, because
 * dropping "whatever covers these columns" would drop indexes this
 * migration never created — on production that would mean deleting
 * `idx_vedio_time` or `idx_pdf_time`.
 */
class LegacyIndexGuard
{
    /**
     * Whether this connection can accept the MySQL-specific index DDL at all.
     *
     * The `main` connection is in-memory SQLite under test — where `SHOW
     * INDEX` and `ALGORITHM=INPLACE` are not valid SQL — and may point at
     * no legacy database at all on a fresh install. Both paths must no-op
     * rather than abort the migration run and take the browser-driven
     * deployment installer down with them.
     */
    public static function applicable(Connection $connection, string $table): bool
    {
        return $connection->getDriverName() === 'mysql'
            && $connection->getSchemaBuilder()->hasTable($table);
    }

    /**
     * Whether the desired index already exists by name, or is covered as a
     * left-prefix of an existing index.
     *
     * @param  list<string>  $columns  ordered, unquoted column names
     */
    public static function alreadyCovered(
        Connection $connection,
        string $table,
        string $indexName,
        array $columns,
    ): bool {
        return self::isCovered(self::existingIndexes($connection, $table), $indexName, $columns);
    }

    /** Whether an index of exactly this name exists — the check `down()` uses. */
    public static function namedIndexExists(Connection $connection, string $table, string $indexName): bool
    {
        return $connection->select(
            sprintf('SHOW INDEX FROM `%s` WHERE Key_name = ?', $table),
            [$indexName],
        ) !== [];
    }

    /**
     * The pure decision, separated from the database so it can be tested
     * exhaustively without a MySQL server.
     *
     * @param  array<string, list<string>>  $existingIndexes  index name => ordered columns
     * @param  list<string>  $columns
     */
    public static function isCovered(array $existingIndexes, string $indexName, array $columns): bool
    {
        // An empty desired list would be a left-prefix of everything, which
        // would silently skip a real index. Treat it as never covered.
        if ($columns === []) {
            return false;
        }

        $normalised = [];

        foreach ($existingIndexes as $name => $existingColumns) {
            $normalised[mb_strtolower((string) $name)] = array_map(mb_strtolower(...), $existingColumns);
        }

        // A name collision is decisive on its own: ADD INDEX would fail with
        // a duplicate-key-name error regardless of what columns it covers.
        if (array_key_exists(mb_strtolower($indexName), $normalised)) {
            return true;
        }

        $desired = array_map(mb_strtolower(...), $columns);

        foreach ($normalised as $existingColumns) {
            if (array_slice($existingColumns, 0, count($desired)) === $desired) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads the table's current indexes as `name => ordered columns`.
     *
     * `SHOW INDEX` rather than an `information_schema` query: it needs no
     * privileges beyond those the application already uses on this
     * connection, and it needs no schema name — which matters because the
     * legacy database is reached through a dedicated connection whose
     * schema differs between environments.
     *
     * @return array<string, list<string>>
     */
    public static function existingIndexes(Connection $connection, string $table): array
    {
        return self::mapShowIndexRows(
            $connection->select(sprintf('SHOW INDEX FROM `%s`', $table))
        );
    }

    /**
     * Turns raw `SHOW INDEX` rows into ordered column lists.
     *
     * Partial-prefix entries (`Sub_part` set, e.g. `title(10)`) **truncate**
     * the index rather than being skipped. An index on `(a, b(10), c)` can
     * fully serve `(a)` but not `(a, b)`, so its usable full-column prefix
     * is `[a]` — dropping the entry instead would collapse the list to
     * `[a, c]` and falsely report `(a, c)` as covered.
     *
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array<string, list<string>>
     */
    public static function mapShowIndexRows(iterable $rows): array
    {
        /** @var array<string, array<int, array{column: string, partial: bool}>> $bySequence */
        $bySequence = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $name = (string) ($row['Key_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $bySequence[$name][(int) ($row['Seq_in_index'] ?? 0)] = [
                'column' => (string) ($row['Column_name'] ?? ''),
                'partial' => ($row['Sub_part'] ?? null) !== null,
            ];
        }

        $indexes = [];

        foreach ($bySequence as $name => $entries) {
            ksort($entries);
            $columns = [];

            foreach ($entries as $entry) {
                if ($entry['partial']) {
                    break;
                }

                $columns[] = $entry['column'];
            }

            $indexes[$name] = $columns;
        }

        return $indexes;
    }
}
