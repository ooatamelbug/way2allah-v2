<?php

namespace App\Domain\Admin\Http\Requests\Telawah;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmEditGroup`'s inputs (`group_edit.htm`: «عنوان القسم» and
 * «التعليق»), under OLD's own field names. OLD validated nothing. Added: a
 * title (an untitled group would render as an empty card), at most the
 * column's 150 characters. No parent field: OLD's form had none, and the
 * group's parent is kept (owner decision, 2026-10-29).
 */
class UpdateTelawahGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'Des' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل عنوان القسم',
            'title.max' => 'عنوان القسم أطول من الحد المسموح به (150 حرف)',
        ];
    }
}
