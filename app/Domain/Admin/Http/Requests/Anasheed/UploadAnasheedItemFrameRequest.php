<?php

namespace App\Domain\Admin\Http\Requests\Anasheed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * OLD `UploadBanner()`'s checks (`d_config.php`), with OLD's messages: a
 * file was chosen, its extension is `jpg`, and it is within OLD's server limit
 * (`admin_anasheed.frame_max_bytes`, 40 000 bytes). Added: the detected type
 * must be JPEG, not just the name — with its own message, so a file merely
 * named `.jpg` (owner manual review, 2026-10-23) is told apart from a wrong
 * extension.
 */
class UploadAnasheedItemFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $max = (int) config('admin_anasheed.frame_max_bytes');

        return [
            'frame' => ['required', 'file', 'extensions:jpg', 'mimetypes:image/jpeg',
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
            'frame.required' => 'لم يتم إختيار ملف للتحميل',
            'frame.file' => 'لم يتم إختيار ملف للتحميل',
            'frame.uploaded' => 'حجم الملف أكبر من الحد الأقصى المسموح به',
            'frame.extensions' => 'هذا الإمتداد غير مسموح به',
            // A file named .jpg whose detected content is not JPEG (e.g. WebP / PNG saved as .jpg).
            'frame.mimetypes' => 'الملف ليس صورة JPG حقيقية، يرجى حفظ الصورة بصيغة JPG ثم إعادة رفعها',
        ];
    }
}
