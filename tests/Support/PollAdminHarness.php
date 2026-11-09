<?php

namespace Tests\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * OLD Admin Migration Phase 4 — shared setup for the PHP-Nuke poll
 * administration tests. Includes the custom-survey tables so tests can
 * prove poll administration never touches them.
 */
final class PollAdminHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_config' => MainSchema::nukeConfig(),
            'nuke_poll_desc' => MainSchema::nukePollDesc(),
            'nuke_poll_data' => MainSchema::nukePollData(),
            'nuke_pollcomments' => MainSchema::nukePollcomments(),
            'nuke_survey' => MainSchema::nukeSurvey(),
            'nuke_survey_questions' => MainSchema::nukeSurveyQuestions(),
            'nuke_survey_answers' => MainSchema::nukeSurveyAnswers(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        DB::connection('main')->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /** @param list<string> $permissions */
    public static function admin(array $permissions = [], string $aid = 'poll-admin'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x']);
        $admin->assignRole('admin');

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin->fresh();
    }

    public static function superAdmin(string $aid = 'poll-boss'): AdminUser
    {
        $admin = AdminUser::on('main')->create(['aid' => $aid, 'password' => 'x', 'radminsuper' => 1]);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    /**
     * A poll as OLD created it: twelve slots, the given texts in order.
     *
     * @param  list<string>  $texts
     * @param  list<int>  $counts
     * @param  array<string, mixed>  $attributes
     */
    public static function poll(string $title = 'استفتاء', array $texts = ['نعم', 'لا'], array $counts = [], array $attributes = []): int
    {
        $main = DB::connection('main');
        $id = (int) $main->table('nuke_poll_desc')->insertGetId(array_merge([
            'pollTitle' => $title, 'timeStamp' => 1600000000, 'voters' => array_sum($counts),
            'planguage' => 'arabic', 'artid' => 0, 'comments' => 0,
        ], $attributes), 'pollID');

        for ($v = 1; $v <= 12; $v++) {
            $main->table('nuke_poll_data')->insert([
                'pollID' => $id, 'voteID' => $v, 'optionText' => $texts[$v - 1] ?? '', 'optionCount' => $counts[$v - 1] ?? 0,
            ]);
        }

        return $id;
    }

    public static function comment(int $pollId, int $parent = 0, string $text = 'تعليق'): int
    {
        return (int) DB::connection('main')->table('nuke_pollcomments')->insertGetId([
            'pid' => $parent, 'pollID' => $pollId, 'date' => '2011-01-01 10:00:00', 'name' => 'زائر', 'subject' => 'عنوان', 'comment' => $text,
        ], 'tid');
    }

    public static function desc(int $pollId): ?object
    {
        return DB::connection('main')->table('nuke_poll_desc')->where('pollID', $pollId)->first();
    }

    /** @return array<int, string> voteID => text (first row per slot) */
    public static function texts(int $pollId): array
    {
        return DB::connection('main')->table('nuke_poll_data')->where('pollID', $pollId)->orderBy('voteID')->get()
            ->unique('voteID')->mapWithKeys(fn ($r) => [(int) $r->voteID => (string) $r->optionText])->all();
    }

    /** Every poll-domain and custom-survey row — the "nothing else moved" baseline. */
    public static function snapshot(): array
    {
        $main = DB::connection('main');
        $all = fn (string $t, string $order) => $main->table($t)->orderBy($order)->get()->map(fn ($r) => (array) $r)->all();

        return [
            'desc' => $all('nuke_poll_desc', 'pollID'),
            'data' => $main->table('nuke_poll_data')->orderBy('pollID')->orderBy('voteID')->orderBy('optionText')->get()->map(fn ($r) => (array) $r)->all(),
            'comments' => $all('nuke_pollcomments', 'tid'),
            'survey' => $all('nuke_survey', 'id'),
            'survey_questions' => $all('nuke_survey_questions', 'id'),
            'survey_answers' => $all('nuke_survey_answers', 'id'),
        ];
    }

    /** @return list<object> */
    public static function auditRows(): array
    {
        return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
    }

    /** @return list<array{sql: string, bindings: array<int, mixed>}> */
    public static function writesDuring(callable $callback): array
    {
        $writes = [];
        DB::connection('main')->listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });

        $callback();

        return $writes;
    }
}
