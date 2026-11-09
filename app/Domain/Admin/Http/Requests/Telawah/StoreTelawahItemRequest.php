<?php

namespace App\Domain\Admin\Http\Requests\Telawah;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmInsertTelawah`'s inputs (`add_telawa_form.html`), under OLD's
 * own field names; the group is the bound one, not the posted hidden
 * `group_id`. OLD's one refusal is kept with its message (a title over 256
 * bytes: «عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات»).
 *
 * Added, so a broken recitation cannot be created: a title and a link are
 * required; `notes` is `varchar(255) NOT NULL` (OLD's longer text failed the
 * insert under production's strict mode); the size must fit `linksize`
 * (`int(11)`, bytes); a chosen sura must exist in `nuke_telawah_sorahlist`
 * («لا يوجد» posts an empty value, stored as 0 as OLD did).
 */
class StoreTelawahItemRequest extends FormRequest
{
    /** The largest MB value whose byte count fits `linksize` (signed int). */
    public const MAX_SIZE_MB = 2047;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', self::titleLength()],
            'vedio' => ['required', Rule::in(['0', '1'])],
            'Telawahfile' => ['required', 'string', 'max:65535'],
            'Telawahsize' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_SIZE_MB],
            'sorah' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('sorah') > 0, ['exists:main.nuke_telawah_sorahlist,id'])],
            'MoreInfo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::sharedMessages() + [
            'Telawahfile.required' => 'من فضلك أدخل وصلة التلاوة',
            'MoreInfo.max' => 'التعليق على التلاوة أطول من 255 حرف',
        ];
    }

    /** OLD's `strlen($title) > 256` refusal, with its message. */
    public static function titleLength(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && strlen($value) > 256) {
                $fail('عفوا ... العنوان اكبر من الحد المسموح به بقاعدة البيانات');
            }
        };
    }

    /** @return array<string, string> */
    public static function sharedMessages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل عنوان التلاوة',
            'Telawahsize.numeric' => 'الحجم يجب أن يكون رقما بالميجا بايت',
            'Telawahsize.max' => 'الحجم أكبر من الحد المسموح به',
            'sorah.exists' => 'السورة غير موجودة بقواعد البيانات',
        ];
    }
}
