<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmAddGroup`'s inputs (`addgroup.htm`), under OLD's own field
 * names; the parent is the bound group, not a posted id. OLD's own refusal
 * (a duplicate title under the same parent) is applied by
 * InsertAnasheedSubGroupAction. Added: a title, at most the column's 100
 * characters (OLD's `maxlength`), and a chosen channel must exist.
 */
class StoreAnasheedSubGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'Title' => ['required', 'string', 'max:100'],
            'Channel' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Channel') > 0, ['exists:main.nuke_sat_channels,id'])],
            'Des' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'Title.required' => 'من فضلك أدخل عنوان القسم',
            'Title.max' => 'عنوان القسم أطول من الحد المسموح به (100 حرف)',
            'Channel.exists' => 'القناة غير موجودة بقواعد البيانات',
        ];
    }
}
