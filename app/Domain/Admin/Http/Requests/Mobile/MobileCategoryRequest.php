<?php

namespace App\Domain\Admin\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/** Phase 7 — a Mobile category: title (`varchar(50)`), description, and for a new one an optional parent (0 = top level). */
class MobileCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'cdescription' => ['nullable', 'string', 'max:65000'],
            'parentid' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
