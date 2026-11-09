<?php

namespace App\Domain\Admin\Http\Requests\Advfiles;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 5 — the promotional file's payload, as OLD's insert / edit form
 * posted it: `code` (raw HTML, `text`), `audio_url` (`text`), `credit`
 * (int; OLD `intval()`ed it and its credit page accepted digits only, up
 * to 6). Stored verbatim — never rendered unescaped by Laravel.
 */
class AdvfilePayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:65535'],
            'audio_url' => ['nullable', 'string', 'max:65535'],
            'credit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /** @return array{credit: int, code: string, audio_url: string} */
    public function payload(): array
    {
        return [
            'credit' => (int) $this->validated('credit', 0),
            'code' => (string) $this->validated('code', ''),
            'audio_url' => (string) $this->validated('audio_url', ''),
        ];
    }
}
