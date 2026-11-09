<?php

namespace App\Domain\Content\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/** Phase 7 — OLD `ratedownload` form: a rating 1–10 ("--" refused), an optional comment (members only). */
class MobileRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,10'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
