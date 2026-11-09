<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * OLD `UploadIcon()`'s checks (`groupicon.htm`, `d_config.php`), with OLD's
 * messages: a file was chosen, its extension is `jpg`, and it is within
 * OLD's effective limit (`admin_anasheed.icon_max_bytes`, 10 000 bytes —
 * the form's `MAX_FILE_SIZE`). Added: the detected type must be JPEG, not
 * just the name.
 */
class UploadAnasheedGroupIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $max = (int) config('admin_anasheed.icon_max_bytes');

        return [
            'icon' => ['required', 'file', 'extensions:jpg', 'mimetypes:image/jpeg',
                function (string $attribute, mixed $value, \Closure $fail) use ($max) {
                    if ($value instanceof UploadedFile && $value->getSize() > $max) {
                        $fail('حجم الملف أكبر من الحجم المسموح به');
                    }
                }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'icon.required' => 'لم يتم إختيار ملف للتحميل',
            'icon.file' => 'لم يتم إختيار ملف للتحميل',
            'icon.uploaded' => 'حجم الملف أكبر من الحد الأقصى المسموح به',
            'icon.extensions' => 'هذا الإمتداد غير مسموح به',
            'icon.mimetypes' => 'هذا الإمتداد غير مسموح به',
        ];
    }
}
