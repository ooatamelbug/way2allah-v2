<?php

namespace App\Domain\Admin\Http\Requests\Ads;

use App\Domain\Admin\Support\LegacyAds;
use App\Domain\Content\Models\HomeAd;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * F01 / B6a — OLD `add_ads()` / `edit_ads()` fields (`modules/ads/admin/index.php:324-570`,
 * templates `newads.htm` / `editads.htm`), validated server-side (OLD had client-side checks only):
 *
 * | field | OLD | here |
 * |---|---|---|
 * | `name` | required (client) | required, ≤ 255 |
 * | `type` | 1 image / 0 code | required, 0 or 1; type 0 is super-admin only (enforced in the actions) |
 * | `google_code` | stored raw in `image_path` | required for type 0 |
 * | `path_type` | 1 upload / 2 URL | required for type 1 |
 * | `image_file` | any extension | name AND content must be JPEG/PNG/GIF/WebP, ≤ 2 MiB, must decode as an image; no SVG |
 * | `image_url` / `link` | free text | http(s) URL, ≤ 2048, no whitespace or `< > " ' ` \\`; an UNCHANGED stored value in the same role is accepted as is |
 * | `position` | select 1–12 | integer 1–12 |
 * | `ads_show_type` | radio period / numview | required |
 * | `startdate` / `enddate` | Y-m-d | required for period, end ≥ start |
 * | `required_num_view` | intval | required for numview, integer ≥ 1 |
 * | `percentage` | field commented out in both OLD forms | not accepted (see the actions) |
 */
class AdRequest extends FormRequest
{
    public const MAX_IMAGE_KB = 2048;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $existing = $this->existing();

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', 'in:0,1'],
            'google_code' => ['nullable', 'string', 'max:65000', 'required_if:type,0'],
            'path_type' => ['nullable', 'integer', 'in:1,2', 'required_if:type,1'],
            'image_file' => ['nullable', 'file', 'max:'.self::MAX_IMAGE_KB, 'extensions:jpg,jpeg,png,gif,webp', 'mimes:jpg,jpeg,png,gif,webp',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp'],
            // The "unchanged legacy value" exemption applies only to a value stored in the SAME role: an image ad's
            // typed URL. A code ad's `image_path` holds raw HTML and must never be carried over as an image URL.
            'image_url' => ['nullable', 'string', 'max:2048', $this->urlRule(
                $existing !== null && (int) $existing->type === LegacyAds::TYPE_IMAGE && (int) $existing->path_type !== LegacyAds::PATH_UPLOAD
                    ? $existing->image_path : null,
            )],
            'link' => ['nullable', 'string', 'max:2048', $this->urlRule($existing?->link)],
            'position' => ['required', 'integer', 'between:1,12'],
            'show' => ['nullable', 'in:0,1'],
            'ads_show_type' => ['required', 'in:period,numview'],
            'startdate' => ['nullable', 'required_if:ads_show_type,period', 'date_format:Y-m-d'],
            'enddate' => ['nullable', 'required_if:ads_show_type,period', 'date_format:Y-m-d', 'after_or_equal:startdate'],
            'required_num_view' => ['nullable', 'required_if:ads_show_type,numview', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ((int) $this->input('type') !== LegacyAds::TYPE_IMAGE) {
                return;
            }

            $file = $this->file('image_file');
            $pathType = (int) $this->input('path_type');

            if ($pathType === LegacyAds::PATH_UPLOAD) {
                $existing = $this->existing();
                $keepsCurrentUpload = $existing !== null
                    && (int) $existing->type === LegacyAds::TYPE_IMAGE
                    && (int) $existing->path_type === LegacyAds::PATH_UPLOAD
                    && trim((string) $existing->image_path) !== '';

                if (! $file instanceof UploadedFile && ! $keepsCurrentUpload) {
                    $v->errors()->add('image_file', 'من فضلك اختر صورة الإعلان');
                }

                // The type check above is by content; this also requires the bytes to decode as a raster image.
                if ($file instanceof UploadedFile && $file->isValid() && @getimagesize($file->getRealPath()) === false) {
                    $v->errors()->add('image_file', 'الملف ليس صورة صالحة');
                }
            }

            if ($pathType === LegacyAds::PATH_URL && trim((string) $this->input('image_url')) === '') {
                $v->errors()->add('image_url', 'من فضلك اكتب مسار الصورة');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'من فضلك اكتب اسم الإعلان',
            'type.required' => 'من فضلك اختر نوع الإعلان',
            'google_code.required_if' => 'من فضلك اكتب شفرة الإعلان',
            'path_type.required_if' => 'من فضلك اختر مسار الصورة',
            'image_file.extensions' => 'امتداد الملف غير مسموح به (JPG / PNG / GIF / WebP فقط)',
            'image_file.mimes' => 'امتداد الملف غير مسموح به (JPG / PNG / GIF / WebP فقط)',
            'image_file.mimetypes' => 'الملف ليس صورة JPG / PNG / GIF / WebP حقيقية',
            'image_file.max' => 'حجم الصورة أكبر من 2 ميجا',
            'position.required' => 'من فضلك اختر مكان الإعلان',
            'ads_show_type.required' => 'لم يتم إختيار طريقة حساب الإعلان',
            'startdate.required_if' => 'من فضلك اكتب تاريخ بداية الإعلان',
            'enddate.required_if' => 'من فضلك اكتب تاريخ نهاية الإعلان',
            'enddate.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
            'required_num_view.required_if' => 'من فضلك اكتب عدد مرات الظهور',
            'required_num_view.min' => 'عدد مرات الظهور يجب أن يكون 1 على الأقل',
        ];
    }

    /** The record being edited, or null on create. */
    public function existing(): ?HomeAd
    {
        $ad = $this->route('ad');

        return $ad instanceof HomeAd ? $ad : null;
    }

    /**
     * http(s) URL, or — so editing never forces a rewrite of legacy data — exactly the value already stored.
     */
    private function urlRule(?string $stored): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($stored) {
            $value = trim((string) $value);

            if ($value === '' || ($stored !== null && $value === trim($stored))) {
                return;
            }

            // Besides being a real http(s) URL, a NEW value may not contain characters that break out of the quoted
            // attribute / JavaScript string contexts OLD's and Gen 2's public renderers put it in
            // (`href='…'`, `src="…"`, and the position-12 popup's single-quoted JS string).
            $valid = preg_match('#^https?://#i', $value) === 1
                && filter_var($value, FILTER_VALIDATE_URL) !== false
                && preg_match('/[\s\x00-\x1F\x7F<>"\'`\\\\]/u', $value) !== 1;

            if (! $valid) {
                $fail('الرابط يجب أن يبدأ بـ http:// أو https://');
            }
        };
    }
}
