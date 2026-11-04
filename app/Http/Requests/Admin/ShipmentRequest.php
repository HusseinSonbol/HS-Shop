<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'shipping_company_id' => ['required', 'integer', Rule::exists('shipping_companies', 'id')->where('is_active', true)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'customer_shipping_charge' => ['required', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'carrier_cost_actual' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_company_id.required' => __('admin.shipments.validation.company_required'),
            'shipping_company_id.integer' => __('admin.shipments.validation.company_invalid'),
            'shipping_company_id.exists' => __('admin.shipments.validation.company_invalid'),
            'tracking_number.max' => __('admin.shipments.validation.tracking_max'),
            'customer_shipping_charge.required' => __('admin.shipments.validation.customer_charge_required'),
            'customer_shipping_charge.numeric' => __('admin.shipments.validation.amount_invalid'),
            'customer_shipping_charge.min' => __('admin.shipments.validation.amount_invalid'),
            'customer_shipping_charge.regex' => __('admin.shipments.validation.amount_invalid'),
            'carrier_cost_actual.numeric' => __('admin.shipments.validation.amount_invalid'),
            'carrier_cost_actual.min' => __('admin.shipments.validation.amount_invalid'),
            'carrier_cost_actual.regex' => __('admin.shipments.validation.amount_invalid'),
            'description.max' => __('admin.shipments.validation.description_max'),
            'idempotency_key.required' => __('admin.shipments.validation.request_invalid'),
            'idempotency_key.uuid' => __('admin.shipments.validation.request_invalid'),
        ];
    }
}
