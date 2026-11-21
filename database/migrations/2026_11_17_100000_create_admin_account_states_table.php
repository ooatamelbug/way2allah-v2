<?php

use App\Support\Database\NonEmptyTableGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F02 — administrator deactivation state (B3 design review §13.4).
 *
 * On `main` deliberately: that is the schema Gen 2 and OLD already read
 * (`w2acp_db2025-2` in production after the 2026-09-24 cutover), so a later
 * legacy patch can check the same row with its existing connection, and the
 * state row, the `nuke_authors` credential neutralisation and the
 * `admin_audit_log` row are one InnoDB transaction.
 *
 * Charset and collation are explicit: the production schema's default is
 * `latin1`. No foreign key (project rule for the shared legacy schema).
 *
 * Skipped under `runningUnitTests()` exactly like `create_admin_audit_log`:
 * `main` resolves to a real MySQL database during tests, and creating a table
 * there is not idempotent. Tests build it from
 * `MainSchema::adminAccountStates()`; `AdminAccountStateSchemaDriftTest`
 * keeps the two in column agreement.
 */
return new class extends Migration
{
    private const CONNECTION = 'main';

    private const TABLE = 'admin_account_states';

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::TABLE, function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->engine = 'InnoDB';

            // `nuke_authors.id`; one row per admin ever deactivated, kept forever (history).
            $table->unsignedInteger('admin_id')->primary();
            // Non-NULL = deactivated. The only enforcement field.
            $table->unsignedInteger('deactivated_at')->nullable();
            $table->unsignedInteger('deactivated_by')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('reactivated_at')->nullable();
            $table->unsignedInteger('reactivated_by')->nullable();
            $table->unsignedInteger('updated_at');
        });
    }

    /** Never drops the table while it holds rows (same rule as the audit log). */
    public function down(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        NonEmptyTableGuard::assertSafeToDrop(DB::connection(self::CONNECTION), self::TABLE);

        Schema::connection(self::CONNECTION)->dropIfExists(self::TABLE);
    }
};
