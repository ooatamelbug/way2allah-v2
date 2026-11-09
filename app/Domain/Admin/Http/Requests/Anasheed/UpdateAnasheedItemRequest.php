<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmEditAnasheed`'s inputs (production working copy), under OLD's
 * own field names. OLD's one refusal is kept with its message (a title over
 * 250 bytes). Added, as for Add Item: a title and a link are required;
 * `notes` is `varchar(255)`; the size must fit `linksize`; a chosen channel or
 * author must exist; the radios accept only their two values. Whether the
 * group is one OLD's select offered is checked by UpdateAnasheedItemAction.
 */
class UpdateAnasheedItemRequest extends FormRequest
{
    /** The largest MB value whose byte count fits `linksize` (signed int). */
    private const MAX_SIZE_MB = 2047;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && strlen($value) > 250) {
                    $fail('عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات');
                }
            }],
            'vedio' => ['required', Rule::in(['0', '1'])],
            'fixed' => ['nullable', Rule::in(['0', '1'])],
            'is_published' => ['nullable', Rule::in(['0', '1'])],
            'Anasheed_link' => ['required', 'string', 'max:65535'],
            'Channel' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Channel') > 0, ['exists:main.nuke_sat_channels,id'])],
            'Author' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Author') > 0, ['exists:main.nuke_islamic_authors,id'])],
            'Anasheedsize' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_SIZE_MB],
            'group' => ['required', 'integer', 'min:1'],
            'Des' => ['nullable', 'string', 'max:255'],
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
            'title.required' => 'من فضلك أدخل عنوان المادة',
            'Anasheed_link.required' => 'من فضلك أدخل وصلة المادة',
            'Anasheedsize.numeric' => 'الحجم يجب أن يكون رقما بالميجا بايت',
            'Anasheedsize.max' => 'الحجم أكبر من الحد المسموح به',
            'Des.max' => 'التعليق على المادة أطول من 255 حرف',
            'Channel.exists' => 'القناة غير موجودة بقواعد البيانات',
            'Author.exists' => 'الشيخ غير موجود بقواعد البيانات',
        ];
    }
}
