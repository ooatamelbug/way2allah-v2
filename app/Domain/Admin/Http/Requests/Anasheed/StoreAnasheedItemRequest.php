<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmInsertAnasheed`'s inputs (`insertanasheed.htm`, the
 * production-matching working copy), under
 * OLD's own field names; the group is the bound one, not the posted hidden
 * `group_id`. OLD's one refusal is kept with its message (a title over 250
 * bytes: «عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات»).
 *
 * Added, so a broken item cannot be created: a title and a link are
 * required; `notes` is `varchar(255)`; the size must fit `linksize`
 * (`int(11)`, bytes); a chosen channel or author must exist. `Channel` /
 * `Author` may be empty — OLD's «غير مصنف», stored as NULL.
 */
class StoreAnasheedItemRequest extends FormRequest
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
            'Anasheedfile' => ['required', 'string', 'max:65535'],
            'Channel' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Channel') > 0, ['exists:main.nuke_sat_channels,id'])],
            'Author' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('Author') > 0, ['exists:main.nuke_islamic_authors,id'])],
            'Anasheedsize' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_SIZE_MB],
            'MoreInfo' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_index' => ['nullable'],
            'meta_follow' => ['nullable'],
            // Production OLD's «نشر علي المنصة» checkbox (value 1; absent when unchecked).
            'is_published' => ['nullable', Rule::in(['0', '1'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل عنوان المادة',
            'Anasheedfile.required' => 'من فضلك أدخل وصلة المادة',
            'Anasheedsize.numeric' => 'الحجم يجب أن يكون رقما بالميجا بايت',
            'Anasheedsize.max' => 'الحجم أكبر من الحد المسموح به',
            'MoreInfo.max' => 'التعليق على المادة أطول من 255 حرف',
            'Channel.exists' => 'القناة غير موجودة بقواعد البيانات',
            'Author.exists' => 'الشيخ غير موجود بقواعد البيانات',
        ];
    }
}
