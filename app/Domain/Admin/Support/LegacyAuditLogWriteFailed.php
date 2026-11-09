<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminAuditEntry;
use RuntimeException;
use Throwable;

/**
 * OLD Admin Migration Phase 1 — reported, never thrown to the caller, when
 * the optional `nuke_islamic_logs` coexistence write fails. Carries the
 * authoritative entry id so the missing legacy row can be traced from the
 * application log. See `AdminAuditRecorder::mirrorToLegacyLog()`.
 */
final class LegacyAuditLogWriteFailed extends RuntimeException
{
    public function __construct(AdminAuditEntry $entry, Throwable $previous)
    {
        parent::__construct(
            "Legacy nuke_islamic_logs compatibility write failed for admin_audit_log #{$entry->id} "
            ."({$entry->action} {$entry->entity_type}#{$entry->entity_id}). "
            .'The mutation and its authoritative audit row were committed; only the optional legacy row is missing.',
            0,
            $previous,
        );
    }
}
