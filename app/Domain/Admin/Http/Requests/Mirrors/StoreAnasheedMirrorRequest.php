<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 — input for a new Anasheed mirror. See StoreKhotabMirrorRequest
 * for why `authorize()` returns true.
 */
class StoreAnasheedMirrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'link' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            // Megabytes. Capped at 2047 because production's
            // `nuke_anasheed_mirror.linksize` is a signed INT (Pass 3E.1
            // §0.3): 2048 MB in bytes would overflow it, which under strict
            // SQL mode is an error that would abort the whole insert. The
            // cap turns that into a validation message instead.
            'linksize_mb' => ['nullable', 'numeric', 'min:0', 'max:2047'],
            'vedio' => ['required', 'in:0,1'],
            'backupme' => ['nullable', 'boolean'],
        ];
    }
}
