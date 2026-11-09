<?php

namespace App\Domain\Admin\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 3 — the one input every category assignment takes: which category.
 *
 * It must exist in `nuke_w2a_cat`. OLD never checked this (an unknown id was
 * written into the list as-is); requiring it is the plan §17 contract.
 */
class CategoryAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'min:1', 'exists:main.nuke_w2a_cat,id'],
        ];
    }

    public function categoryId(): int
    {
        return (int) $this->validated('category_id');
    }
}
