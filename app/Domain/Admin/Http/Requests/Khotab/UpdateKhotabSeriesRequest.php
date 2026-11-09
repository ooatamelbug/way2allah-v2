<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmEditSeries`'s inputs, under OLD's own field names. OLD's one
 * refusal — an unknown channel for a series with no group — depends on the
 * series' state, so UpdateKhotabSeriesAction applies it with OLD's message.
 * The title length is the column's (`varchar(100)`, strict mode).
 */
class UpdateKhotabSeriesRequest extends FormRequest
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
            'C1' => ['nullable'],
            'ramadan' => ['nullable'],
            'group' => ['nullable', 'integer', 'min:0'],
            'Channel' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
            'Des' => ['nullable', 'string'],
        ];
    }
}
