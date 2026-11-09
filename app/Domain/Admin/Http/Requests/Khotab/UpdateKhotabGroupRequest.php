<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmEditGroup`'s inputs, under OLD's own field names. OLD validated
 * only the channel (an unknown one: «القناة غير موجودة بقواعد البيانات»);
 * the title length is the column's (`varchar(100)`, strict mode), which OLD
 * would have failed on at the database instead.
 */
class UpdateKhotabGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
            'C1' => ['nullable'],
            'Channel' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Channel') > 0, ['exists:main.nuke_sat_channels,id'])],
            'Des' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['Channel.exists' => 'القناة  غير موجودة بقواعد البيانات'];
    }
}
