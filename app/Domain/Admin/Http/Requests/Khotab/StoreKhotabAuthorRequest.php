<?php

namespace App\Domain\Admin\Http\Requests\Khotab;

/**
 * OLD `ConfirmInsertAuthor`'s inputs — the edit page's fields without
 * «مخفي» (OLD's add form had none; a new author is visible). OLD's own
 * refusal (the same name with the same title) is applied by
 * InsertKhotabAuthorAction.
 */
class StoreKhotabAuthorRequest extends UpdateKhotabAuthorRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['C1' => true]);
    }
}
