<?php

namespace App\Domain\Admin\Http\Requests\Fatwa;

use Illuminate\Foundation\Http\FormRequest;

/**
 * C1 «اضافة موضوع للتصنيف» — OLD `fatwa_add.htm`'s fields under OLD's names,
 * with OLD's two refusals and messages: an empty name («من فضلك أدخل اسم
 * الموضوع») and one under 3 characters. The category is the bound one, not
 * a posted `main_fatwa`; the same-category duplicate is checked by
 * InsertFatwaTopicAction. `topic_name` is `varchar(255)`.
 */
class StoreFatwaTopicRequest extends FormRequest
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
        ] + self::seoRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'من فضلك أدخل اسم الموضوع',
            'title.min' => '(*) أقل عدد حروف يجب ادخالها هو 3 حروف',
            'title.max' => 'اسم الموضوع أطول من 255 حرف',
        ];
    }

    /** The description / SEO block every Fatwa topic and question form carries. @return array<string, mixed> */
    public static function seoRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_index' => ['nullable', 'in:1'],
            'meta_follow' => ['nullable', 'in:1'],
        ];
    }
}
