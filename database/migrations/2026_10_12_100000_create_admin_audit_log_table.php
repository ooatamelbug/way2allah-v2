<?php

use App\Support\Database\NonEmptyTableGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OLD Admin Migration Phase 0 — the authoritative Laravel administration
 * audit trail.
 *
 * **On the `main` connection, deliberately, and this is the one place this
 * project puts a new table there.** Spatie's own tables live on
 * `config('database.default')` per ADR-0011 and must stay there: they hold
 * authorization *configuration*. This table holds *records of content
 * mutations*, and Pass 3E.1's invariant I-10 requires each mirror
 * operation to write the mirror row and the parent counter inside one
 * transaction. Those rows are on `main`. A cross-connection audit write
 * cannot join that transaction, so an audit row on the default connection
 * could survive a rolled-back mutation — the exact silent-divergence
 * failure the legacy log already has (3E.2 §1.5: untransacted, written
 * after the mutation, failure discarded). Co-locating with the data it
 * describes is what makes "audit participates in rollback" achievable at
 * all.
 *
 * Column shape answers 3E.2 §8.1's "must remain observable" list while
 * structurally refusing §8.2's defects:
 *
 * - **No free-text description column exists.** The legacy `extrainfo`
 *   carried raw mirror URLs (twice per Khotab insert, unescaped in all
 *   three Anasheed calls) plus HTML markup, because it was a rendered
 *   display string. Before/after detail goes in `metadata` as structured
 *   JSON instead, so there is no field for a URL or a tag to leak *into*.
 * - `entity_type` + `entity_id` are both required and `entity_id` is a
 *   plain integer. Legacy's `type='mirror'` row carried the *parent's* id
 *   because `$id` had been reassigned and `sql_nextid()` was commented
 *   out; a required, typed pair cannot drift that way unnoticed.
 * - `actor_admin_id` and `actor_aid` are both stored: the id for joins,
 *   the aid for legibility when an admin is later renamed (Generation 2's
 *   AdminCP renames `aid` across tables, so an aid alone is not stable).
 *
 * Non-nullable `action`/`module`/`entity_type` are what make a malformed
 * write fail loudly rather than record an unattributable row.
 *
 * **Unguarded `create()`, deliberately.** An earlier draft wrapped this in
 * a `hasTable()` early return, by false analogy to the E-04/E-05/E-08/E-12
 * performance-index migrations' `LegacyIndexGuard`. That guard exists for a
 * genuinely different situation: those migrations *alter* pre-existing
 * legacy tables, production already carried structurally equivalent indexes
 * under other names, and MySQL permits duplicate indexes with a warning
 * rather than an error — so an unguarded `CREATE INDEX` would have looked
 * like success while adding real cost.
 *
 * None of that applies here. This table is new and nothing outside this
 * programme creates it; `CREATE TABLE` against an existing name is a hard
 * error, not a warning, so there is no silent-duplication hazard to guard
 * against; and migration tracking already prevents ordinary re-execution.
 * The project's own three `create`-table migrations (`cache`, `jobs`,
 * `permission_tables`) use no such guard either.
 *
 * The guard was also actively harmful: if `admin_audit_log` already existed
 * with a *different* shape — a partially-applied deploy, or a hand-created
 * table — it would have returned silently and recorded the migration as
 * applied, leaving the application running against a schema it does not
 * expect. Failing loudly on that conflict is the correct outcome.
 * `down()` keeps `dropIfExists`, matching `create_jobs_table`.
 *
 * **Skipped under `runningUnitTests()`, and this is not the removed guard
 * in disguise.** This is the first migration in the project to *create* a
 * table on `main` — every earlier `main` migration only adds an index to a
 * table that already exists. That difference matters during tests:
 * `phpunit.xml` overrides `DB_CONNECTION`/`DB_DATABASE` to sqlite
 * `:memory:` but deliberately does not override `DB_MAIN_*`, so `main`
 * still resolves to a real MySQL database while `RefreshDatabase` runs
 * `migrate`. The index migrations tolerate that because adding an index is
 * idempotent; creating a table is not, and it would leave a real table
 * behind in a shared legacy database on the first run and then fail on
 * every run after it.
 *
 * Unlike a `hasTable()` guard, this cannot mask a schema conflict in
 * production: `runningUnitTests()` is false there, so a pre-existing
 * `admin_audit_log` still fails the migration loudly. Tests build the
 * table from `Tests\Support\Fixtures\MainSchema::adminAuditLog()`
 * instead, and `AdminAuditSchemaDriftTest` asserts that fixture and this
 * migration define the same columns, so the two cannot drift apart
 * unnoticed.
 */
return new class extends Migration
{
    private const CONNECTION = 'main';

    private const TABLE = 'admin_audit_log';

    public function up(): void
    {
        // See the class docblock: `main` points at a real MySQL database
        // during tests, and creating a table there is not idempotent.
        if (app()->runningUnitTests()) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');

            // Actor. `nuke_authors.id` is an unsignedInteger (increments),
            // so the FK column matches that width rather than defaulting
            // to bigint. No foreign-key constraint: `main` is a shared
            // legacy database and this project adds none there.
            $table->unsignedInteger('actor_admin_id');
            $table->string('actor_aid', 150);

            // What happened, and where. `module` is the same namespace
            // segment the Spatie permissions use (`khotab`, `anasheed`),
            // so an entry can be traced back to the permission that
            // allowed it.
            $table->string('action', 64);
            $table->string('module', 64);

            // What it happened to.
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');

            // Structured before/after and any other per-action detail.
            // JSON, never a rendered string.
            $table->json('metadata')->nullable();

            // Request context. Nullable because console-originated
            // mutations legitimately have no request.
            $table->string('ip', 45)->nullable();

            $table->unsignedInteger('created_at');

            // Three access patterns this trail actually has: "what
            // happened to this record", "what did this admin do", and
            // "what happened recently".
            $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
            $table->index(['actor_admin_id', 'created_at'], 'idx_audit_actor');
            $table->index('created_at', 'idx_audit_created');
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
