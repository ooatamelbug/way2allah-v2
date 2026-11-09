<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 — input for an Anasheed mirror edit.
 *
 * `linksize` here is **bytes**, not megabytes: OLD's edit form pre-filled
 * the stored byte value and the handler only `intval()`d it. Whether the
 * submitted size is honoured at all depends on the mirror's `down` state —
 * see UpdateAnasheedMirrorAction's docblock.
 */
class UpdateAnasheedMirrorRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'linksize' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'vedio' => ['required', 'in:0,1'],
            'backupme' => ['nullable', 'boolean'],
        ];
    }
}
