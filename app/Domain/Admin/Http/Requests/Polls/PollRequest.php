<?php

namespace App\Domain\Admin\Http\Requests\Polls;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 4 — input for creating or editing a poll.
 *
 * Lengths are the real columns' (and OLD's form `maxlength`s):
 * `pollTitle varchar(100)`, `optionText char(50)`. Choices are keyed by
 * their slot, `options[1]` … `options[12]`; any may be blank, as in OLD.
 * The title is required: OLD's form did not enforce one, but a poll with no
 * question is unusable in every list that identifies polls by title
 * (plan §23.5).
 */
class PollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'pollTitle' => ['required', 'string', 'max:100'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<int, string> slot => text, only slots 1–12 */
    public function options(): array
    {
        $options = [];
        foreach ((array) $this->validated('options', []) as $slot => $text) {
            if (is_numeric($slot) && (int) $slot >= 1 && (int) $slot <= 12) {
                $options[(int) $slot] = (string) $text;
            }
        }

        return $options;
    }
}
