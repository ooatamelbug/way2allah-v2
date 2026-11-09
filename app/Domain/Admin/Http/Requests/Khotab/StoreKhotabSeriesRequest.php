<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmInsertSeries`'s inputs from a group page. Author, group, media
 * type and channel are the group's own (OLD's form fixed them), so they are
 * not inputs here. OLD's form capped the title at 35 characters
 * (`maxlength`); the server cap is the column's `varchar(100)`.
 */
class StoreKhotabSeriesRequest extends FormRequest
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
            'MoreInfo' => ['nullable', 'string'],
        ];
    }
}
