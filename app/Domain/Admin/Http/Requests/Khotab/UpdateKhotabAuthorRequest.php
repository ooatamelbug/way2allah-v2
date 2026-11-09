<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmEditAuthor`'s inputs, under OLD's own field names. OLD
 * validated nothing; the lengths are the columns' (`name varchar(100)`,
 * `prename varchar(20)`, strict mode), which OLD would have failed on at the
 * database instead.
 */
class UpdateKhotabAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'PreName' => ['nullable', 'string', 'max:20'],
            'Name' => ['nullable', 'string', 'max:100'],
            'Des' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'block' => ['nullable', 'string'],
            'C1' => ['nullable'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
        ];
    }
}
