<?php

namespace App\Domain\Admin\Http\Requests\Mobile;

use App\Domain\Content\Http\Requests\Mobile\MobileMaterialFieldsRequest;

/**
 * Phase 7 — a material as staff add, approve or edit it: the member fields
 * plus, on edit, the hit count (OLD's edit form exposed it).
 */
class MobileMaterialRequest extends MobileMaterialFieldsRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return parent::rules() + ['hits' => ['nullable', 'integer', 'min:0', 'max:2147483647']];
    }

    public function hits(): ?int
    {
        $hits = $this->validated('hits');

        return $hits === null ? null : (int) $hits;
    }
}
