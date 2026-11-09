<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmAddGroup`'s inputs, under OLD's own field names. OLD's own
 * refusal (a duplicate title) is applied by InsertKhotabAuthorGroupAction;
 * the title length is the column's (`varchar(100)`, OLD's `maxlength`). OLD
 * did not check that the channel exists, and neither does this.
 */
class StoreKhotabAuthorGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'Title' => ['nullable', 'string', 'max:100'],
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
