<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 — the target of a Khotab mirror re-parent. OLD required the
 * target item to exist and did nothing otherwise; here a missing target is
 * a validation error instead of a silent no-op.
 */
class MoveKhotabMirrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'target_item_id' => ['required', 'integer', 'min:1', 'exists:main.nuke_islamic_khotab,id'],
        ];
    }
}
