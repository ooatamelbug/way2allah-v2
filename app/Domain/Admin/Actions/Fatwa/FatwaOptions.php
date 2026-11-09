<?php

namespace App\Domain\Admin\Actions\Fatwa;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The choices OLD's Fatwa admin forms offered, in OLD's order:
 * `list_all_auther_as_dropdown_menu()` (sheikhs, `ORDER BY BINARY name`),
 * the channel select (`ORDER BY BINARY(title)`, «بدون قناة» first), the
 * topic pickers (`ORDER BY TRIM(topic_name)`) and the question pickers
 * (`ORDER BY TRIM(question_text)`). SQLite (tests) has no BINARY.
 */
final class FatwaOptions
{
    /** @return Collection<int, \stdClass> rows of id, name */
    public static function authors(): Collection
    {
        return DB::connection('main')->table('nuke_islamic_authors')->orderByRaw(self::binary('name'))->get(['id', 'name']);
    }

    /** @return Collection<int, \stdClass> rows of id, title */
    public static function channels(): Collection
    {
        return DB::connection('main')->table('nuke_sat_channels')->orderByRaw(self::binary('title'))->get(['id', 'title']);
    }

    /** @return Collection<int, \stdClass> rows of id, topic_name */
    public static function topics(): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_topics')->orderByRaw('TRIM(topic_name) ASC')->get(['id', 'topic_name']);
    }

    /** @return Collection<int, \stdClass> rows of id, question_text */
    public static function generalQuestions(): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_general_questions')->orderByRaw('TRIM(question_text) ASC')->get(['id', 'question_text']);
    }

    /**
     * The listed rows of a pipe relationship, in STORED order (missing ids
     * are shown by id so the admin sees them).
     *
     * @return list<array{id: int, title: string}>
     */
    public static function linked(PipeIdList $ids, string $table, string $titleColumn): array
    {
        $titles = DB::connection('main')->table($table)->whereIn('id', $ids->ids())->pluck($titleColumn, 'id');

        return array_map(fn (int $id) => ['id' => $id, 'title' => (string) ($titles[$id] ?? '#'.$id)], $ids->ids());
    }

    private static function binary(string $column): string
    {
        return DB::connection('main')->getDriverName() === 'sqlite' ? "{$column} ASC" : "BINARY {$column} ASC";
    }
}
