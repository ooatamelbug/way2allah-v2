<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

/**
 * OLD `ConfirmInsertKhotab`'s inputs from a series page: the group page's
 * fields plus `Channel`, which OLD's form submitted only for a series with no
 * group and no channel (InsertKhotabSeriesItemAction decides whether it is
 * used). OLD did not check that the channel exists, and neither does this.
 */
class StoreKhotabSeriesItemRequest extends StoreKhotabItemRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + ['Channel' => ['nullable', 'integer', 'min:0']];
    }
}
