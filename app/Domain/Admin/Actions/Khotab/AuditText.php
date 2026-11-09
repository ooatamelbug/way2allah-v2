<?php

namespace App\Domain\Admin\Actions\Khotab;

/**
 * Free-text columns (titles, descriptions, notes, SEO text) may legitimately
 * hold markup, and the audit recorder refuses markup in metadata (3E.2
 * §8.2). Such values are therefore recorded as a digest — enough to prove
 * what changed and to match a later value — never as the raw string.
 */
final class AuditText
{
    /** @return array{sha1: string, length: int} */
    public static function digest(?string $value): array
    {
        $value = (string) $value;

        return ['sha1' => sha1($value), 'length' => mb_strlen($value)];
    }

    /**
     * The changed columns between two snapshots; free-text columns digested.
     *
     * @param  array<string, scalar|null>  $before
     * @param  array<string, scalar|null>  $after
     * @param  list<string>  $textColumns
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function changes(array $before, array $after, array $textColumns): array
    {
        $changes = [];

        foreach ($after as $column => $value) {
            $old = $before[$column] ?? null;

            if ((string) $old === (string) $value) {
                continue;
            }

            $changes[$column] = in_array($column, $textColumns, true)
                ? ['from' => self::digest((string) $old), 'to' => self::digest((string) $value)]
                : ['from' => $old, 'to' => $value];
        }

        return $changes;
    }
}
