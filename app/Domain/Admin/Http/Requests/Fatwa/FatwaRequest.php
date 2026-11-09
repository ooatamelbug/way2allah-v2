<?php

namespace App\Domain\Admin\Http\Requests\Fatwa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Q1 «اضافة فتوى للسؤال» and A4 «تعديل» — OLD `fatwa_question_add.htm` /
 * `fatwa_question_edit.htm`'s fields under OLD's names: authers,
 * fatwa_text, fatwa_answer_text, audio_or_video, media_link, media_size
 * (MB), channel («مكان إصدار الفتوى»; 0 = «بدون قناة») and fatwa_date.
 *
 * OLD's client-side rules are enforced on the server: a sheikh and the
 * fatwa text are required; the size is a number (required on add, as OLD's
 * add form). Added: the sheikh must exist (OLD accepted «أختر اسم الشيخ» =
 * 0), a channel other than 0 must exist, the date is a real Y-m-d date, the
 * media link fits `varchar(255)`. A size lookup (X2) is never required.
 */
class FatwaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adding = $this->isMethod('post');

        return [
            'authers' => ['required', 'integer', 'min:1', Rule::exists('main.nuke_islamic_authors', 'id')],
            'fatwa_text' => ['required', 'string', 'max:65535'],
            'fatwa_answer_text' => ['nullable', 'string', 'max:65535'],
            'audio_or_video' => ['required', Rule::in(['audio', 'video'])],
            'media_link' => ['nullable', 'string', 'max:255'],
            'media_size' => [$adding ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'channel' => ['required', 'integer', 'min:0', Rule::when((int) $this->input('channel') > 0, [Rule::exists('main.nuke_sat_channels', 'id')])],
            'fatwa_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'authers.required' => 'أختر اسم الشيخ',
            'authers.min' => 'أختر اسم الشيخ',
            'authers.exists' => 'الشيخ المختار غير موجود',
            'fatwa_text.required' => 'من فضلك أدخل نص الفتوى',
            'audio_or_video.*' => 'اختر نوع المادة',
            'media_link.max' => 'رابط المادة أطول من 255 حرف',
            'media_size.required' => 'من فضلك أدخل حجم المادة بالميجا بايت',
            'media_size.numeric' => 'حجم المادة يجب أن يكون رقما بالميجا بايت',
            'media_size.min' => 'حجم المادة يجب أن يكون رقما بالميجا بايت',
            'media_size.max' => 'حجم المادة أكبر من الحد المسموح به',
            'channel.exists' => 'القناة المختارة غير موجودة',
            'fatwa_date.date_format' => 'تاريخ إصدار الفتوى غير صحيح',
        ];
    }
}
