<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * OLD Admin Migration Phase 0 — the authoritative recorder for
 * administration mutations.
 *
 * Pass 3E.2's E4 made this a migration requirement rather than a choice:
 * the legacy trail is live and current (253,499 rows, 87,547 of them
 * `add_mirror`, most recent 2026-10-12), and Laravel had no audit facility
 * at all. This is that facility. It is deliberately not an event-sourcing
 * system, not a queue, and not a generic activity log — one method that
 * writes one row.
 *
 * Three legacy properties are reproduced because production readers depend
 * on them (3E.2 §8.1): the action vocabulary (`add_mirror`, `edit_mirror`,
 * `del_mirror`, `move_mirror`), a `module` discriminator, and an
 * entity-type/entity-id target pair.
 *
 * Four legacy defects are refused (3E.2 §8.2):
 *
 * - **Silent failure.** Legacy discarded `sql_query()`'s result, so a
 *   failed log left a silent, unattributed mutation. Nothing here is
 *   wrapped in a try/catch — a persistence failure propagates, and
 *   because callers run inside a transaction (I-10) it takes the mutation
 *   down with it. Failing loudly is the point.
 * - **Untransacted writes.** The authoritative insert is a plain insert on
 *   `main` with no transaction of its own, so it enlists in whatever
 *   transaction the caller already opened. (The *optional* legacy write is
 *   isolated in a savepoint — see mirrorToLegacyLog().)
 * - **Target mis-attribution.** `entityId` is a required int. Legacy's
 *   `type='mirror'` row carried the parent's id because the variable had
 *   been reassigned; there is no code path here that can supply a
 *   plausible-but-wrong id by default.
 * - **Raw URL / HTML leakage.** There is no free-text field to leak into
 *   (the table has no description column), and `assertStructuredMetadata()`
 *   below additionally rejects HTML-looking strings, so a caller cannot
 *   smuggle a rendered display string in through `metadata`.
 *
 * URLs themselves are *allowed* in metadata and are expected for a mirror
 * edit's before/after pair — a link is legitimate structured data about a
 * link change. What is refused is a rendered string with markup around it,
 * which is what the legacy `extrainfo` actually was.
 */
class AdminAuditRecorder
{
    public function __construct(
        private readonly LegacyAuditLogCompatibilityWriter $legacyWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata  Structured only — see assertStructuredMetadata().
     *
     * @throws InvalidArgumentException when the entry would be unattributable or unstructured.
     */
    public function record(
        AdminUser $actor,
        string $action,
        string $module,
        string $entityType,
        int $entityId,
        array $metadata = [],
        ?string $ip = null,
    ): AdminAuditEntry {
        $this->assertNonEmpty('action', $action);
        $this->assertNonEmpty('module', $module);
        $this->assertNonEmpty('entityType', $entityType);

        if ($entityId <= 0) {
            throw new InvalidArgumentException(
                'AdminAuditRecorder: entityId must be a positive integer; '.
                'an unattributable audit entry is worse than none.'
            );
        }

        $this->assertStructuredMetadata($metadata);

        $entry = new AdminAuditEntry;
        $entry->forceFill([
            'actor_admin_id' => (int) $actor->id,
            'actor_aid' => (string) $actor->aid,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip' => $ip,
            'created_at' => time(),
        ])->save();

        $this->mirrorToLegacyLog($entry);

        return $entry;
    }

    /**
     * The optional `nuke_islamic_logs` coexistence write, isolated so it
     * can neither break nor be confused with the authoritative record.
     *
     * **Phase 1 decision (supersedes the Phase 0 note that this write was
     * "inside the caller's transaction like everything else").** Phase 0
     * had no mutation caller, so the question was academic. With real
     * mirror mutations, leaving it unisolated meant an *optional* legacy
     * log — a table this project does not own — could roll back a real
     * content change and its authoritative audit row. That inverts the
     * priority: the legacy log exists to help OLD's views during
     * coexistence, not to gate Laravel administration.
     *
     * So the write runs in a **savepoint** (a nested transaction on `main`):
     *
     * - on success it commits atomically with the mutation, exactly as
     *   before;
     * - on failure only the savepoint rolls back — the mutation and the
     *   authoritative `admin_audit_log` row still commit;
     * - the failure is **not silent**: it goes to `report()`, i.e. the
     *   application's exception log, with the authoritative entry id so the
     *   missing legacy row can be identified.
     *
     * Only the legacy write is isolated. A failure of the authoritative
     * insert above still propagates and rolls the whole mutation back
     * (Phase 0's loud-failure rule is unchanged for the record that
     * matters). Default-OFF, so in normal operation this returns at once.
     */
    private function mirrorToLegacyLog(AdminAuditEntry $entry): void
    {
        if (! $this->legacyWriter->enabled()) {
            return;
        }

        try {
            DB::connection('main')->transaction(fn () => $this->legacyWriter->mirror($entry));
        } catch (Throwable $e) {
            report(new LegacyAuditLogWriteFailed($entry, $e));
        }
    }

    private function assertNonEmpty(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("AdminAuditRecorder: {$field} must not be empty.");
        }
    }

    /**
     * Metadata must be a flat-ish structured map, not a rendered string.
     *
     * The check is narrow on purpose: it rejects HTML tags in string
     * values, which is precisely what the legacy `extrainfo` contained
     * (`<a href=...>` wrappers around titles and links). It does not
     * reject URLs — those are legitimate before/after values for a link
     * change — and it does not try to be a general sanitizer.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function assertStructuredMetadata(array $metadata): void
    {
        array_walk_recursive($metadata, function (mixed $value, int|string $key): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/<[a-z!\/][^>]*>/i', $value) === 1) {
                throw new InvalidArgumentException(
                    "AdminAuditRecorder: metadata['{$key}'] contains markup. ".
                    'Audit metadata is structured data, not a rendered display string — '.
                    'this is the legacy `extrainfo` defect (3E.2 §8.2) and is refused.'
                );
            }
        });
    }
}
