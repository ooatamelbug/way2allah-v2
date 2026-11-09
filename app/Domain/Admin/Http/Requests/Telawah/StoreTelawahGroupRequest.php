<?php

namespace App\Domain\Admin\Http\Requests\Telawah;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OLD `ConfirmAddGroup`'s inputs (`add_group.htm`: «الاسم» / «العنوان» and
 * «التعليق»), under OLD's own field names, for both «إضافة قارىء» and
 * «إضافة قسم فرعى»; the parent is the bound group (or none, on Main), not the
 * posted hidden `id`. OLD's own refusal (a duplicate title under the same
 * parent) is applied by InsertTelawahGroupAction. Added: a title, at most the
 * column's 150 characters (`varchar(150)`).
 */
class StoreTelawahGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'Title' => ['required', 'string', 'max:150'],
            'Des' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'Title.required' => 'من فضلك أدخل العنوان',
            'Title.max' => 'العنوان أطول من الحد المسموح به (150 حرف)',
        ];
    }
}
