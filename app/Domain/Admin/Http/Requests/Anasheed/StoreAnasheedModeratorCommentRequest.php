<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;

/** OLD `comment_admin.htm`'s one field: the comment (`text` column). */
class StoreAnasheedModeratorCommentRequest extends FormRequest
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
