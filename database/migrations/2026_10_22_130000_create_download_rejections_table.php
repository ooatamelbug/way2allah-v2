<?php

use App\Support\Database\NonEmptyTableGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global download capacity (2026-10-22) — operational telemetry for downloads
 * refused because the site's ONE download-capacity pool
 * (`DownloadCapacityGuard`) was full. One row per refusal, written by
 * `DownloadRejectionRecorder`. Not the admin audit log.
 *
 * Generic on purpose: any endpoint that joins the pool records here, told
 * apart by `module` / `resource_type` / `variant`. It replaces the never-
 * deployed Khotab-only `khotab_download_rejections` design.
 *
 * A new Laravel-owned table; no legacy table is altered. It lives on `main`
 * for the same reason `admin_audit_log` does: `main` is the one connection
 * known to be the production MySQL database. It follows that migration's
 * conventions exactly — unix-time `created_at` (integer), no foreign keys in
 * the shared legacy database, an unguarded `create()` (a conflicting existing
 * table must fail loudly), and the same `runningUnitTests()` skip, because
 * `main` points at a real MySQL database while the suite runs; the tests build
 * the table from `MainSchema::downloadRejections()` and
 * `DownloadRejectionSchemaDriftTest` keeps the two in agreement.
 *
 * Indexes answer the questions this table exists for — refusals over time, per
 * module and resource, per client IP, per reason — and nothing more.
 */
return new class extends Migration
{
    private const CONNECTION = 'main';

    private const TABLE = 'download_rejections';

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('created_at');

            // Why it was refused (DOWNLOAD_CAPACITY_FULL), and by which pool.
            $table->string('reason', 32);
            $table->string('pool', 32);

            // What was asked for. For a mirror, `resource_id` is the parent
            // item and `variant_id` the mirror.
            $table->string('module', 32);
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->string('variant', 32);
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('requested_url', 2048);
            // The stored link, as already loaded — never resolved remotely.
            $table->string('resolved_download_url', 2048)->nullable();

            // Size only when already known: `db`, else NULL / `unknown`.
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_size_source', 16);

            // Who asked. `client_ip` is Laravel's trusted-proxy-resolved IP;
            // `forwarded_for` is the raw header, a diagnostic only.
            $table->string('client_ip', 45);
            $table->string('forwarded_for', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 1024)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->boolean('is_contextual_admin')->default(false);

            // The pool's state when it refused.
            $table->unsignedInteger('active_downloads');
            $table->unsignedInteger('download_limit');
            $table->unsignedSmallInteger('retry_after_seconds');
            $table->string('request_id', 36)->nullable();

            $table->index('created_at', 'idx_dr_created');
            $table->index(['module', 'resource_id', 'created_at'], 'idx_dr_resource');
            $table->index(['client_ip', 'created_at'], 'idx_dr_ip');
            $table->index(['reason', 'created_at'], 'idx_dr_reason');
        });
    }

    /**
     * Owner-approved 2026-11-08: never drops the table while it holds rows — NonEmptyTableGuard raises
     * before the drop, so the rollback fails loudly and the migration stays recorded. An empty (or
     * missing) table is dropped as before.
     */
    public function down(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        NonEmptyTableGuard::assertSafeToDrop(DB::connection(self::CONNECTION), self::TABLE);

        Schema::connection(self::CONNECTION)->dropIfExists(self::TABLE);
    }
};
