<?php

namespace App\Domain\Admin\Http\Requests\Cds;

use App\Domain\Admin\Actions\Cds\CdImageList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Phase 6 — a CD release, as OLD's insert / edit form posted it
 * (`insertcd.htm`, `editcd.htm`):
 *
 * - `title`: required, at most 100 (the form's `maxlength` and edit's own
 *   server check; create's separate 200 check could never be reached
 *   through the form);
 * - `links[n][title]` / `links[n][url]`: the download links. The first
 *   row was required by the form; every row is a pair — both or neither.
 *   Neither part may contain a comma (the list delimiter), and the joined
 *   titles must fit `cd varchar(255)`;
 * - images: jpg / jpeg / png / gif, as OLD's extension check; at most five
 *   in total;
 * - `group_id`: optional; an unknown group becomes the main list (0), as OLD.
 */
class CdReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $image = ['file', 'mimes:jpg,jpeg,png,gif', 'max:5120'];

        return [
            'title' => ['required', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:0'],
            'links' => ['required', 'array'],
            'links.*.title' => ['nullable', 'string', 'max:255', 'not_regex:/,/'],
            'links.*.url' => ['nullable', 'string', 'max:2048', 'not_regex:/,/'],
            'images' => ['nullable', 'array', 'max:'.CdImageList::MAX_IMAGES],
            'images.*' => $image,
            'existing' => ['nullable', 'array'],
            'existing.*.name' => ['required', 'string'],
            'existing.*.keep' => ['nullable', 'boolean'],
            'existing.*.file' => ['nullable', ...$image],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'links.*.title.not_regex' => 'لا يمكن أن يحتوي عنوان الرابط على فاصلة (,)',
            'links.*.url.not_regex' => 'لا يمكن أن يحتوي الرابط على فاصلة (,)',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $pairs = 0;
            foreach ((array) $this->input('links', []) as $i => $row) {
                $title = trim((string) ($row['title'] ?? ''));
                $url = trim((string) ($row['url'] ?? ''));
                if (($title === '') !== ($url === '')) {
                    $validator->errors()->add("links.{$i}", 'كل رابط يحتاج إلى عنوان ورابط معاً');
                }
                $pairs += $title !== '' && $url !== '' ? 1 : 0;
            }
            if ($pairs === 0) {
                $validator->errors()->add('links', 'أضف رابطاً واحداً على الأقل بعنوانه');
            }
            if (mb_strlen(implode(',', array_column($this->links(), 'title'))) > 255) {
                $validator->errors()->add('links', 'مجموع عناوين الروابط أطول من الحد المسموح به (255 حرفاً)');
            }
            $kept = collect($this->existingImages())->filter(fn ($e) => $e['keep'] || $e['file'] !== null)->count();
            if ($kept + count($this->addedImages()) > CdImageList::MAX_IMAGES) {
                $validator->errors()->add('images', 'الحد الأقصى خمس صور للإسطوانة');
            }
        }];
    }

    /** @return list<array{title: string, url: string}> */
    public function links(): array
    {
        $pairs = [];
        foreach ((array) $this->input('links', []) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($title !== '' && $url !== '') {
                $pairs[] = ['title' => $title, 'url' => $url];
            }
        }

        return $pairs;
    }

    /** @return list<UploadedFile> */
    public function addedImages(): array
    {
        return array_values(array_filter((array) $this->file('images', []), fn ($f) => $f instanceof UploadedFile));
    }

    /** @return list<array{name: string, keep: bool, file: UploadedFile|null}> */
    public function existingImages(): array
    {
        $entries = [];
        foreach ((array) $this->input('existing', []) as $i => $entry) {
            $file = $this->file("existing.{$i}.file");
            $entries[] = [
                'name' => (string) ($entry['name'] ?? ''),
                'keep' => (bool) ($entry['keep'] ?? false),
                'file' => $file instanceof UploadedFile ? $file : null,
            ];
        }

        return $entries;
    }
}
