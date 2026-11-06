<?php

namespace App\Http\Requests\Admin;

use App\Support\ReturnCaseLifecycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('returns.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ReturnCaseLifecycle::STATUSES)],
            'unused_confirmed' => ['sometimes', 'accepted'],
            'inspection_note' => ['nullable', 'string', 'max:3000'],
            'replacements' => ['sometimes', 'array'],
            'replacements.*.return_item_id' => ['required_with:replacements', 'integer', 'distinct', 'exists:return_items,id'],
            'replacements.*.variant_id' => ['required_with:replacements', 'integer', 'exists:product_variants,id'],
            'replacements.*.quantity' => ['required_with:replacements', 'numeric', 'gt:0', 'max:999999999999999.999', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('admin.returns.validation.status_invalid'), 'status.in' => __('admin.returns.validation.status_invalid'),
            'unused_confirmed.accepted' => __('admin.returns.validation.unused_required'),
            'inspection_note.max' => __('admin.returns.validation.inspection_note_max'),
            'replacements.*.return_item_id.exists' => __('admin.returns.validation.replacement_invalid'),
            'replacements.*.variant_id.exists' => __('admin.returns.validation.replacement_invalid'),
            'replacements.*.quantity.numeric' => __('admin.returns.validation.quantity_invalid'),
            'replacements.*.quantity.gt' => __('admin.returns.validation.quantity_invalid'),
        ];
    }
}
