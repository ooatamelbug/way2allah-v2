<?php

namespace App\Domain\Admin\Http\Requests\Cds;

use Illuminate\Foundation\Http\FormRequest;

/** Phase 6 — OLD Order_w2acd_in_group: a position per release (`position[<id>]`), lowest shown first. */
class CdOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'position' => ['required', 'array'],
            'position.*' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return list<int> ids, first = shown first; ties keep the submitted order */
    public function orderedIds(): array
    {
        $positions = [];
        $i = 0;
        foreach ((array) $this->validated('position') as $id => $position) {
            $positions[] = ['id' => (int) $id, 'position' => (int) $position, 'i' => $i++];
        }
        usort($positions, fn ($a, $b) => [$a['position'], $a['i']] <=> [$b['position'], $b['i']]);

        return array_column($positions, 'id');
    }
}
