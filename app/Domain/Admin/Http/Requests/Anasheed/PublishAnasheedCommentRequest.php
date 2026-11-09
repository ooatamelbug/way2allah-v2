<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;

/** OLD `addcomment.htm`'s «نشر التعليق للمادة»: the (edited) comment text. */
class PublishAnasheedCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['comment' => ['required', 'string', 'max:65535']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['comment.required' => 'من فضلك اكتب نص التعليق'];
    }
}
