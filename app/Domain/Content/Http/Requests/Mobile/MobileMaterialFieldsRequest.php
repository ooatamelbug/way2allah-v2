<?php

namespace App\Domain\Content\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 7 — the material fields a member submits or proposes (OLD `Add`,
 * `modifydownloadrequestS`): title, URL and description are required (OLD
 * refused each when empty); the rest optional. Lengths are the real
 * columns'. Corrected: OLD silently truncated a URL longer than the
 * 100-character column (68 stored URLs are cut off); a longer URL is refused
 * here, and it must be an http(s) URL because it becomes a download link.
 * OLD's size field accepted "1.234,5" style digits and stripped the
 * separators; the same is done here.
 */
class MobileMaterialFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['filesize' => str_replace(['.', ','], '', (string) $this->input('filesize', ''))]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'cid' => ['required', 'integer', 'exists:main.nuke_downloads_categories,cid'],
            'title' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:100', 'url:http,https'],
            'description' => ['required', 'string', 'max:65000'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:100'],
            'filesize' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'version' => ['nullable', 'string', 'max:10'],
            'homepage' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} */
    public function fields(): array
    {
        $v = $this->validated();

        return [
            'cid' => (int) $v['cid'],
            'title' => (string) $v['title'],
            'url' => (string) $v['url'],
            'description' => (string) $v['description'],
            'name' => (string) ($v['name'] ?? ''),
            'email' => (string) ($v['email'] ?? ''),
            'filesize' => (int) ($v['filesize'] ?? 0),
            'version' => (string) ($v['version'] ?? ''),
            'homepage' => (string) ($v['homepage'] ?? ''),
        ];
    }
}
