<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReturnCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('returns.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'in:damaged,manufacturing_defect'],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
            'unused_claimed' => ['accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999999999.999', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.required' => __('admin.returns.validation.reason_invalid'), 'reason_code.in' => __('admin.returns.validation.reason_invalid'),
            'reason.required' => __('admin.returns.validation.reason_required'), 'reason.min' => __('admin.returns.validation.reason_required'),
            'reason.max' => __('admin.returns.validation.reason_max'), 'unused_claimed.accepted' => __('admin.returns.validation.unused_required'),
            'items.required' => __('admin.returns.validation.item_required'), 'items.min' => __('admin.returns.validation.item_required'),
            'items.*.order_item_id.required' => __('admin.returns.validation.item_invalid'), 'items.*.order_item_id.exists' => __('admin.returns.validation.item_invalid'),
            'items.*.quantity.required' => __('admin.returns.validation.quantity_invalid'), 'items.*.quantity.numeric' => __('admin.returns.validation.quantity_invalid'),
            'items.*.quantity.min' => __('admin.returns.validation.quantity_invalid'),
        ];
    }
}
