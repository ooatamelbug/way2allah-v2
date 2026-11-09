<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmEditGroup`'s inputs, under OLD's own field names. OLD
 * validated nothing. Added: a title (an untitled group would render as an
 * empty card and breadcrumb), its length is the column's (`varchar(100)` —
 * OLD's `maxlength="150"` would have failed at the database under strict
 * mode), and a chosen channel must exist. Whether the parent is one OLD
 * offered is checked by UpdateAnasheedGroupAction.
 */
class UpdateAnasheedGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'parent_id' => ['required', 'integer', 'min:0'],
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
            'title.required' => 'من فضلك أدخل عنوان القسم',
            'title.max' => 'عنوان القسم أطول من الحد المسموح به (100 حرف)',
            'Channel.exists' => 'القناة غير موجودة بقواعد البيانات',
        ];
    }
}
