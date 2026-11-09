<?php

namespace App\Domain\Admin\Http\Requests\Mirrors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input for a Telawah mirror edit — OLD's form fields exactly
 * (`templates/Telawah/admin_edit_mirror.htm`): `comment`, `link`, `vedio`
 * (stored as `video`), `backupme`. `link` and `comment` are VARCHAR(255) in
 * production, so longer input is refused rather than truncated.
 */
class UpdateTelawahMirrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'link' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:255'],
            'vedio' => ['required', 'in:0,1'],
            'backupme' => ['nullable', 'boolean'],
        ];
    }
}
