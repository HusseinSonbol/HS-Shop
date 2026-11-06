<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('expenses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'cost_type' => ['required', Rule::in(['product', 'packaging', 'advertising', 'other'])],
            'basis' => ['required', Rule::in(['estimate', 'actual'])],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id', 'required_if:cost_type,product'],
            'shipment_id' => ['prohibited'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['cost_type.in' => __('admin.returns.validation.cost_type_invalid'), 'basis.in' => __('admin.returns.validation.cost_basis_invalid'), 'amount.required' => __('admin.returns.validation.amount_invalid'), 'amount.numeric' => __('admin.returns.validation.amount_invalid'), 'amount.regex' => __('admin.returns.validation.amount_invalid'), 'order_item_id.required_if' => __('admin.returns.validation.item_invalid'), 'order_item_id.exists' => __('admin.returns.validation.item_invalid'), 'description.required' => __('admin.returns.validation.reason_required'), 'description.min' => __('admin.returns.validation.reason_required'), 'idempotency_key.required' => __('admin.shipments.validation.request_invalid'), 'idempotency_key.uuid' => __('admin.shipments.validation.request_invalid')];
    }
}
