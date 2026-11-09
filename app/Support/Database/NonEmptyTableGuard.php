<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Rollback protection for Laravel-owned tables that live on the shared legacy `main` database
 * (`admin_audit_log`, `download_rejections`; owner-approved 2026-11-08). A migration's `down()` may drop such a
 * table only while it is empty: a table holding rows makes the rollback fail with a clear message, so no audit
 * history or rejection record is lost by an accidental `migrate:rollback`. Because the exception is raised
 * before the drop, the migration stays recorded as applied and the schema stays consistent. Dropping a
 * non-empty table is a separate, owner-approved DBA action (after a backup).
 */
final class NonEmptyTableGuard
{
    public static function assertSafeToDrop(Connection $connection, string $table): void
    {
        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        if ($connection->table($table)->limit(1)->exists()) {
            throw new RuntimeException(
                "Refusing to drop `{$table}`: it contains data. Rolling this migration back would destroy it; "
                .'dropping it requires a separate, owner-approved DBA action after a backup.'
            );
        }
    }
}
