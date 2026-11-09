<?php

namespace App\Domain\Admin\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 2 — filters for the read-only view of OLD's `nuke_islamic_logs`.
 *
 * Reproduces OLD's own `listlogs()` filter set exactly
 * (`includes/logs.php:103`): one module, plus optional moderator (`aid`),
 * `action`, `target_id` and `type`. OLD always scoped a view to one module,
 * so `module` is constrained to the three modules that had an OLD log view
 * — `Khotab`, `Anasheed` and `Cat`, the callers of `listlogs()` — spelled as
 * OLD wrote them (`$module_name` is the module directory name). A fixed list
 * also avoids a `DISTINCT` scan over a 253,499-row table on every request.
 */
class LegacyAuditLogFilterRequest extends FormRequest
{
    public const MODULES = ['Khotab', 'Anasheed', 'Cat'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'module' => ['nullable', 'in:'.implode(',', self::MODULES)],
            'aid' => ['nullable', 'string', 'max:150'],
            'action' => ['nullable', 'string', 'alpha_dash', 'max:64'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'alpha_dash', 'max:64'],
        ];
    }
}
