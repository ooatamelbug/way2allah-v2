<?php

namespace App\Domain\Admin\Http\Requests\Advfiles;

use Illuminate\Foundation\Http\FormRequest;

/** Phase 5 — OLD's bulk credit form: `credit[<item id>] = <number>`, digits only, at most 6. */
class AdvfileCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'credit' => ['required', 'array'],
            'credit.*' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /** @return array<int, int> */
    public function credits(): array
    {
        $credits = [];
        foreach ((array) $this->validated('credit') as $id => $value) {
            if (is_numeric($id) && (int) $id > 0) {
                $credits[(int) $id] = (int) $value;
            }
        }

        return $credits;
    }
}
