<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

use App\Domain\Admin\Actions\Khotab\KhotabAdditionalAuthorCopies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmInsertKhotab`'s inputs from a group page, under OLD's field
 * names. OLD's own refusals (title under 3 or over 250 bytes, a link already
 * used by the same author) are applied by InsertKhotabItemAction, in OLD's
 * order and with OLD's messages. Only what strict MySQL would otherwise fail
 * on is added here: `notes` is `varchar(255)` (OLD's form said «لا تزيد عن 250
 * حرف»), and a location must exist when one is chosen.
 */
class StoreKhotabItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'link' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'size' => ['nullable', 'numeric', 'min:0'],
            'original_size' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'MoreInfo' => ['nullable', 'string', 'max:255'],
            'Location' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Location') > 0, ['exists:main.nuke_islamic_locations,id'])],
            'newslist' => ['nullable'],
            'fixed' => ['nullable'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
            'archive_gif' => ['nullable', 'string', 'max:2048'],
            'archive_frame' => ['nullable', 'string', 'max:2048'],
            // Multi-author creation (2026-11-25): optional; existence, visibility and "not the primary" are checked
            // by the action under the author locks (KhotabAdditionalAuthorCopies).
            'additional_authors' => ['nullable', 'array', 'max:'.KhotabAdditionalAuthorCopies::max()],
            'additional_authors.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'additional_authors.array' => 'قائمة الدعاة المشاركين غير صحيحة.',
            'additional_authors.max' => 'يمكن اختيار '.KhotabAdditionalAuthorCopies::max().' دعاة مشاركين على الأكثر.',
            'additional_authors.*.integer' => 'قائمة الدعاة المشاركين غير صحيحة.',
            'additional_authors.*.min' => 'قائمة الدعاة المشاركين غير صحيحة.',
            'additional_authors.*.distinct' => 'تم اختيار داعية أكثر من مرة.',
        ];
    }
}
