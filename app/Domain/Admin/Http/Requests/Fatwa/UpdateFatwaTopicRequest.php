<?php

namespace App\Domain\Admin\Http\Requests\Fatwa;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T2 «تعديل الموضوع» — OLD `fatwa_edit.htm`'s fields under OLD's names
 * (`topicname`). OLD validated nothing; a name is now required and fits
 * `varchar(255)`.
 */
class UpdateFatwaTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topicname' => ['required', 'string', 'max:255'],
        ] + StoreFatwaTopicRequest::seoRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'topicname.required' => 'من فضلك أدخل اسم الموضوع',
            'topicname.max' => 'اسم الموضوع أطول من 255 حرف',
        ];
    }
}
