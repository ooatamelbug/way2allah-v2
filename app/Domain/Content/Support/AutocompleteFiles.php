<?php

namespace App\Domain\Content\Support;

/**
 * The search autocomplete lists (`w2a_autocomplete/*.txt`) — OLD
 * `update_ajax_files_with_data()` / `select_authors_ajax()`
 * (`way2allah_functions.php`). Each file is OLD's format: every entry
 * followed by `,|^`, the header splitting on that separator.
 *
 * The directory is `media.autocomplete_root`, never a hard-coded server path.
 */
final class AutocompleteFiles
{
    public const SEPARATOR = ',|^';

    public static function path(string $file): string
    {
        return rtrim((string) config('media.autocomplete_root'), '/').'/'.$file;
    }

    /** @return list<string> the file's entries, in order (none when the file is missing or empty) */
    public static function entries(string $file): array
    {
        $path = self::path($file);
        $contents = is_file($path) ? trim((string) file_get_contents($path)) : '';

        return $contents !== '' ? explode(self::SEPARATOR, $contents) : [];
    }

    /** @param iterable<string> $entries */
    public static function write(string $file, iterable $entries): void
    {
        $data = '';
        foreach ($entries as $entry) {
            $data .= $entry.self::SEPARATOR;
        }

        $path = self::path($file);
        $directory = dirname($path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        // OLD wrote nothing when there was no data (update_ajax_files_with_data's empty() guard).
        if ($data !== '') {
            file_put_contents($path, $data, LOCK_EX);
        }
    }
}
