<?php

namespace App\Domain\Admin\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 2 — filters for the authoritative Laravel audit trail
 * (`admin_audit_log`). Every filter maps to one explicit `where`; there is
 * no free-form search. Access is enforced by the route's
 * `admin.role:super-admin`, so `authorize()` adds nothing.
 */
class AdminAuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'module' => ['nullable', 'string', 'alpha_dash', 'max:64'],
            'action' => ['nullable', 'string', 'alpha_dash', 'max:64'],
            'entity_type' => ['nullable', 'string', 'alpha_dash', 'max:64'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'actor' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
