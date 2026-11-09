<?php

namespace App\Domain\Admin\Http\Requests\Fatwa;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Q2 «تعديل السؤال» — OLD `fatwa_general_question_edit.htm`'s fields under
 * OLD's names. OLD validated nothing; the question text is now required and
 * fits `varchar(255)`. The question's topics are not part of this form.
 */
class UpdateFatwaGeneralQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ] + StoreFatwaTopicRequest::seoRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل نص السؤال',
            'title.max' => 'نص السؤال أطول من 255 حرف',
        ];
    }
}
