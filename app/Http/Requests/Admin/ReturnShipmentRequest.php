<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'leg_type' => ['required', Rule::in(['customer_return', 'exchange'])],
            'shipping_company_id' => ['required', 'integer', Rule::exists('shipping_companies', 'id')->where('is_active', true)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'carrier_cost_actual' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['leg_type.in' => __('admin.returns.validation.shipment_data_invalid'), 'shipping_company_id.exists' => __('admin.returns.validation.shipment_data_invalid'), 'carrier_cost_actual.numeric' => __('admin.returns.validation.amount_invalid'), 'carrier_cost_actual.regex' => __('admin.returns.validation.amount_invalid'), 'idempotency_key.required' => __('admin.shipments.validation.request_invalid'), 'idempotency_key.uuid' => __('admin.shipments.validation.request_invalid')];
    }
}
