<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

/**
 * OLD `ConfirmInsertSeries`'s inputs from an author page: the group page's
 * fields plus the channel, which OLD's form left free there (not checked
 * against `nuke_sat_channels`, as in OLD).
 */
class StoreKhotabAuthorSeriesRequest extends StoreKhotabSeriesRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + ['Channel' => ['nullable', 'integer', 'min:0']];
    }
}
