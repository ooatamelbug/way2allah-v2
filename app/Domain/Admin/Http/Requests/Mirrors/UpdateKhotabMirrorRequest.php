<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 — input for a Khotab mirror edit. No size field: Khotab's edit
 * never wrote `linksize` (3E.1 C10).
 *
 * `link` is nullable on edit, unlike insert, so a pre-existing mirror whose
 * link is already empty stays editable rather than being blocked by a rule
 * it never had to satisfy (3E.1 §6.3).
 */
class UpdateKhotabMirrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'link' => ['nullable', 'string', 'max:2048'],
            'comment' => ['nullable', 'string', 'max:255'],
            'vedio' => ['required', 'in:0,1'],
            'backupme' => ['nullable', 'boolean'],
        ];
    }
}
