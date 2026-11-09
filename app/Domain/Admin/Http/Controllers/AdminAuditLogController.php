<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Http\Requests\Reports\AdminAuditLogFilterRequest;
use App\Domain\Admin\Http\Requests\Reports\LegacyAuditLogFilterRequest;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Support\AuditEntityLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 2 — the administration action-log views.
 * Read-only: no method here writes anything.
 *
 * Two pages, kept explicitly separate rather than merged into one
 * pretend-homogeneous model:
 *
 * - `index()` — the **authoritative** Laravel trail, `admin_audit_log`
 *   (Phase 0). Structured fields, real actor relation, entity links.
 * - `legacy()` — a read-only view of OLD's `nuke_islamic_logs`, reproducing
 *   OLD's own `listlogs()` (`includes/logs.php:103`): one module at a time,
 *   **`hidden = '0'` always**, optional moderator/action/target/type
 *   filters, newest first. It is shown for coexistence only and is never
 *   the source for Laravel reporting.
 *
 * Both are super-admin only (route `admin.role:super-admin`), matching
 * OLD, whose `listlogs()` returned nothing unless `radminsuper === 1`.
 * OLD's two other exits from that check — a hard-coded exception for one
 * named account and a `$Pass` bypass parameter — are not reproduced.
 */
class AdminAuditLogController
{
    private const PER_PAGE = 50;

    /** OLD `listlogs()` used `$perpage = 200` (`includes/logs.php:115`). */
    private const LEGACY_PER_PAGE = 200;

    public function index(AdminAuditLogFilterRequest $request): View
    {
        $filters = $request->validated();

        $query = AdminAuditEntry::query()->with('actor');

        foreach (['module', 'action', 'entity_type'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (int) $filters['entity_id']);
        }

        if (! empty($filters['actor'])) {
            $query->where('actor_admin_id', (int) $filters['actor']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()->getTimestamp());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()->getTimestamp());
        }

        // created_at then id: stable (id is unique) and served by Phase 0's
        // idx_audit_created / idx_audit_actor.
        $entries = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.audit.index', [
            'entries' => $entries,
            'links' => AuditEntityLinks::resolve($entries->getCollection()),
            'modules' => AdminAuditEntry::query()->distinct()->orderBy('module')->pluck('module'),
            'filters' => $filters,
        ]);
    }

    public function legacy(LegacyAuditLogFilterRequest $request): View
    {
        $filters = $request->validated();
        $module = $filters['module'] ?? LegacyAuditLogFilterRequest::MODULES[0];

        $query = DB::connection('main')->table('nuke_islamic_logs')
            ->select(['id', 'aid', 'module', 'type', 'target_id', 'action', 'extrainfo', 'mytime', 'ip'])
            ->where('module', $module)
            ->where('hidden', '0');

        if (! empty($filters['aid'])) {
            $query->where('aid', $filters['aid']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['target_id'])) {
            $query->where('target_id', (int) $filters['target_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Cursor pagination on the primary key: each page is `id < cursor`,
        // so a deep page costs the same as the first. OLD instead fetched
        // every matching row just to count them, then used OFFSET.
        $rows = $query->orderByDesc('id')->cursorPaginate(self::LEGACY_PER_PAGE)->withQueryString();

        return view('admin.audit.legacy', [
            'rows' => $rows,
            'module' => $module,
            'modules' => LegacyAuditLogFilterRequest::MODULES,
            'filters' => $filters,
        ]);
    }
}
