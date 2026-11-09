<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmEditkhotab`'s inputs, under OLD's own field names. OLD
 * validated nothing; the lengths here are the columns' (`title varchar(250)`,
 * `notes varchar(255) NOT NULL`, strict mode), which OLD would have failed on
 * at the database instead.
 */
class UpdateKhotabItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'khotab_link' => ['nullable', 'string', 'max:65535'],
            'khotabsize' => ['nullable', 'numeric', 'min:0', 'max:2047'],
            'Des' => ['nullable', 'string', 'max:255'],
            'newslist' => ['nullable'],
            'fixed' => ['nullable'],
            'hidden' => ['nullable'],
            'Channel' => ['nullable', 'integer', 'min:0'],
            'Location' => ['nullable', 'integer', 'min:0'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
        ];
    }
}
