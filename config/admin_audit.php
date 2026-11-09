<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy `nuke_islamic_logs` compatibility writes
    |--------------------------------------------------------------------------
    |
    | OLD Admin Migration Phase 0. When true, every authoritative
    | `AdminAuditEntry` is additionally mirrored into the legacy
    | `nuke_islamic_logs` table so OLD's moderator-log views can still see
    | administration performed in Laravel while both systems are live.
    |
    | Defaults to FALSE: Phase 0 ships no mutations, so there is nothing to
    | mirror yet, and enabling it earlier would write into a 253,499-row
    | production table with no caller to validate the row shape. Phase 1
    | turns this on with a config change, not a refactor.
    |
    | This is never Laravel's authoritative audit store — `admin_audit_log`
    | is. See LegacyAuditLogCompatibilityWriter's docblock for the full
    | reasoning and for what it deliberately does not write.
    |
    */

    'legacy_compatibility_writes' => (bool) env('ADMIN_AUDIT_LEGACY_COMPAT_WRITES', false),

];
