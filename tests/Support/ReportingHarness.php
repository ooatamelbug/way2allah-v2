<?php

namespace Tests\Support;

use App\Support\Permission\Role;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;

/**
 * OLD Admin Migration Phase 2 — setup for the reporting tests.
 *
 * `InMemoryConnection::setup()` recreates the whole `main` connection, so
 * every table the Phase 2 pages read must be created in one call — the
 * Phase 1 tables plus the author/group/series tables the lost-link report
 * joins. Item/mirror/admin helpers are reused from MirrorAdminHarness
 * rather than duplicated (that file is frozen with Phase 1 and is not
 * edited here).
 */
final class ReportingHarness
{
    public static function setUp(): void
    {
        InMemoryConnection::setup('main', [
            'nuke_authors' => MainSchema::nukeAuthors(),
            'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
            'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
            'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
            'nuke_anasheed_mirror' => MainSchema::nukeAnasheedMirror(),
            'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
            'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
            'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
            'admin_audit_log' => MainSchema::adminAuditLog(),
            'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
        ]);

        (new AdminPermissionSeeder)->run();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        config(['admin_audit.legacy_compatibility_writes' => false]);
    }

    /**
     * Inserted directly, bypassing AdminAuditRecorder, so tests can also
     * store values the recorder would refuse (e.g. markup) and prove the
     * view still renders them safely.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function auditEntry(array $attributes = []): int
    {
        $row = array_merge([
            'actor_admin_id' => 1,
            'actor_aid' => 'someone',
            'action' => 'edit_mirror',
            'module' => 'khotab',
            'entity_type' => 'mirror',
            'entity_id' => 1,
            'metadata' => null,
            'ip' => '192.0.2.1',
            'created_at' => 1760000000,
        ], $attributes);

        if (is_array($row['metadata'])) {
            $row['metadata'] = json_encode($row['metadata'], JSON_UNESCAPED_UNICODE);
        }

        return (int) DB::connection('main')->table('admin_audit_log')->insertGetId($row);
    }

    /** @param array<string, mixed> $attributes */
    public static function legacyLog(array $attributes = []): int
    {
        return (int) DB::connection('main')->table('nuke_islamic_logs')->insertGetId(array_merge([
            'aid' => 'old-moderator',
            'module' => 'Khotab',
            'type' => 'mirror',
            'target_id' => 1,
            'action' => 'edit_mirror',
            'extrainfo' => 'تعديل وصلة',
            'mytime' => 1700000000,
            'ip' => '198.51.100.1',
            'hidden' => '0',
        ], $attributes));
    }

    /**
     * Every write statement issued on either connection while $callback runs.
     *
     * @return list<string>
     */
    public static function writesDuring(\Closure $callback): array
    {
        $writes = [];
        $listener = function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        };

        DB::connection('main')->listen($listener);
        DB::connection()->listen($listener);

        $callback();

        return $writes;
    }
}
