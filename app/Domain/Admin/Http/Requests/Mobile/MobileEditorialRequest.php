<?php

namespace App\Domain\Admin\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/** Phase 7 — the staff review of a material: title (`varchar(100)`) and text. */
class MobileEditorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'editorialtitle' => ['required', 'string', 'max:100'],
            'editorialtext' => ['required', 'string', 'max:65000'],
        ];
    }
}
