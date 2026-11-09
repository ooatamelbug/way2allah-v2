<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 — input for a new Khotab mirror. These are the project's first
 * FormRequests; until now controllers validated inline.
 *
 * `authorize()` returns true deliberately. Authorization happens in two
 * places that already exist and must not disagree: the route's
 * `admin.permission:khotab.addmirror` middleware, and
 * `AdminRecordAuthorizer` at the action boundary. A third check here could
 * only drift from those two.
 */
class StoreKhotabMirrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Required on insert: an empty mirror is not a mirror, and an
            // empty string would also collide with OLD's author-scoped
            // duplicate guard against every other empty link by that author.
            'link' => ['required', 'string', 'max:2048'],
            'comment' => ['nullable', 'string', 'max:255'],
            // Megabytes, as OLD's insert form took them. The ceiling is a
            // sanity bound (1 TB), not a legacy rule; the column is BIGINT.
            'linksize_mb' => ['nullable', 'numeric', 'min:0', 'max:1048576'],
            'vedio' => ['required', 'in:0,1'],
            'backupme' => ['nullable', 'boolean'],
        ];
    }
}
