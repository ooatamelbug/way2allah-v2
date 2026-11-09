<?php

namespace App\Domain\Admin\Http\Requests\Fatwa;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T1 «اضافة سؤال للموضوع» — OLD `fatwa_general_question_add.htm`'s fields
 * under OLD's names, with OLD's two refusals and messages: an empty question
 * and one under 3 characters (OLD's `mb_strlen <= 2`). The topic is the
 * bound one; the duplicate is checked by InsertFatwaGeneralQuestionAction.
 * `question_text` is `varchar(255)`.
 */
class StoreFatwaGeneralQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
        ] + StoreFatwaTopicRequest::seoRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل نص السؤال',
            'title.min' => '(*) أقل عدد حروف يجب ادخالها هو 3 حروف',
            'title.max' => 'نص السؤال أطول من 255 حرف',
        ];
    }
}
