<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminAuditEntry;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 0 — the bounded `nuke_islamic_logs`
 * coexistence decision, implemented and **default-OFF**.
 *
 * The question the master plan left to this phase: while OLD remains
 * active, should migrated Laravel mutations also write a compatibility
 * entry into the legacy log? Decided from evidence already in hand (3E.2
 * §8.4), with no new investigation:
 *
 * **For writing it.** `nuke_islamic_logs` is shared live state, not an
 * OLD-private store: OLD writes it (253,499 rows, latest 2026-10-12),
 * Generation 2's AdminCP maintains its `aid` integrity on admin rename,
 * and OLD's moderator-log views read it. During side-by-side operation
 * both systems can mutate the same mirrors, so a Laravel-only trail is
 * incomplete in both directions — it cannot answer "who changed this
 * mirror" for an OLD-made change, and OLD's reader cannot see Laravel's.
 *
 * **For not writing it by default.** Phase 0 ships no mutations, so
 * nothing calls this yet either way; turning it on before Phase 1's row
 * shape is settled would mean new writes into a 253,499-row production
 * table with no caller to validate them. The legacy schema is also a
 * constraint we do not want to inherit as authoritative.
 *
 * **Resolution:** build the adapter now, isolated and behind a config
 * flag, defaulting to off. Phase 1 enables it with a config change rather
 * than a refactor, and retiring it when OLD goes away is deleting this
 * file plus one config key. It is explicitly **not** Laravel's
 * authoritative store — `AdminAuditEntry` is, and this class only ever
 * reads from an already-persisted entry.
 *
 * What it writes, and what it refuses to write:
 *
 * - `aid`, `module`, `type`, `target_id`, `action`, `mytime`, `ip`,
 *   `hidden` — the columns legacy readers filter and render on.
 * - `extrainfo` is written as a **plain, markup-free summary** built from
 *   the structured entry, never a rendered `<a href=...>` string. OLD's
 *   moderator-log view will therefore show a less decorated line for
 *   Laravel-written rows. That is a deliberate, accepted display
 *   degradation (plan correction 7) and not a functional break.
 * - `target_id` comes from the authoritative entry's `entity_id`, so the
 *   legacy mis-attribution defect is not reproduced even in the
 *   compatibility row.
 */
class LegacyAuditLogCompatibilityWriter
{
    private const TABLE = 'nuke_islamic_logs';

    /**
     * Phase 1. Laravel's authoritative `module` is the lowercase Spatie
     * namespace segment (`khotab`), but OLD wrote `$module_name`, which is
     * `basename(dirname(__FILE__))` — the module *directory* name,
     * capitalised (`Khotab`, `Anasheed`). OLD's moderator-log views filter
     * on that exact string, so a compatibility row is only visible to them
     * if it uses the legacy spelling. Unknown modules pass through as-is.
     */
    private const LEGACY_MODULE_NAMES = [
        'khotab' => 'Khotab',
        'anasheed' => 'Anasheed',
    ];

    public function enabled(): bool
    {
        return (bool) config('admin_audit.legacy_compatibility_writes', false);
    }

    /**
     * Mirror an already-persisted authoritative entry into the legacy log.
     *
     * No try/catch *here* — this method throws on failure. The caller,
     * `AdminAuditRecorder::mirrorToLegacyLog()`, runs it inside a savepoint
     * and reports the failure, so a failed legacy write neither rolls back
     * the mutation nor disappears silently (Phase 1 decision; see that
     * method's docblock).
     */
    public function mirror(AdminAuditEntry $entry): void
    {
        if (! $this->enabled()) {
            return;
        }

        DB::connection('main')->table(self::TABLE)->insert([
            'aid' => $entry->actor_aid,
            'module' => self::LEGACY_MODULE_NAMES[$entry->module] ?? $entry->module,
            'type' => $entry->entity_type,
            'target_id' => $entry->entity_id,
            'action' => $entry->action,
            'extrainfo' => $this->plainSummary($entry),
            'mytime' => $entry->created_at,
            'ip' => (string) ($entry->ip ?? ''),
            'hidden' => '0',
        ]);
    }

    /**
     * A short, markup-free line. Deliberately does not interpolate any
     * metadata *value* — only its keys — so a URL held legitimately in
     * structured metadata cannot reach this shared, rendered column.
     */
    private function plainSummary(AdminAuditEntry $entry): string
    {
        $summary = "[laravel] {$entry->action} {$entry->entity_type}#{$entry->entity_id}";

        $keys = array_keys($entry->metadata ?? []);

        if ($keys !== []) {
            $summary .= ' ('.implode(', ', $keys).')';
        }

        return $summary;
    }
}
