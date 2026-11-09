<?php

namespace App\Domain\Admin\Http\Requests\Telawah;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OLD `ConfirmEditTelawah`'s inputs (`edit_telawah.htm`), under OLD's own
 * field names, with Add's rules (StoreTelawahItemRequest): OLD's 256-byte
 * title refusal; title and link required; `notes` at most 255; the size fits
 * `linksize`; a chosen sura exists. Whether the group is one OLD's select
 * offered is checked by UpdateTelawahItemAction.
 */
class UpdateTelawahItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', StoreTelawahItemRequest::titleLength()],
            'vedio' => ['required', Rule::in(['0', '1'])],
            'Telawah_link' => ['required', 'string', 'max:65535'],
            'Telawahsize' => ['nullable', 'numeric', 'min:0', 'max:'.StoreTelawahItemRequest::MAX_SIZE_MB],
            'sorah' => ['nullable', 'integer', 'min:0', Rule::when((int) $this->input('sorah') > 0, ['exists:main.nuke_telawah_sorahlist,id'])],
            'group' => ['required', 'integer', 'min:1'],
            'Des' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return StoreTelawahItemRequest::sharedMessages() + [
            'Telawah_link.required' => 'من فضلك أدخل وصلة التلاوة',
            'Des.max' => 'التعليق على التلاوة أطول من 255 حرف',
        ];
    }
}
