<?php

namespace App\Domain\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A public poll comment submission (preview or post). `subject` is limited to
 * the column's 60 characters (OLD's form allowed 85, which the column
 * truncated); `op` is OLD's two submit buttons.
 */
class PollCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'op' => ['required', 'in:preview,ok'],
            'subject' => ['nullable', 'string', 'max:60'],
            'comment' => ['required', 'string', 'max:20000'],
            'pid' => ['nullable', 'integer', 'min:0'],
            'xanonpost' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'string'],
            'order' => ['nullable'],
            'thold' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['subject' => 'الموضوع', 'comment' => 'التعليق'];
    }
}
