<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'rates' => ['sometimes', 'array', 'max:60'],
            'rates.*.code' => ['nullable', 'uuid'],
            'rates.*.governorate_ar' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'rates.*.governorate_en' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'rates.*.charge' => ['required', 'numeric', 'min:0', 'decimal:0,4', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'rates.array' => __('admin.shipping_rates.validation.invalid'),
            'rates.max' => __('admin.shipping_rates.validation.too_many'),
            'rates.*.code.uuid' => __('admin.shipping_rates.validation.invalid'),
            'rates.*.governorate_ar.required' => __('admin.shipping_rates.validation.name_required'),
            'rates.*.governorate_ar.string' => __('admin.shipping_rates.validation.invalid'),
            'rates.*.governorate_ar.max' => __('admin.shipping_rates.validation.name_max'),
            'rates.*.governorate_ar.distinct' => __('admin.shipping_rates.validation.duplicate_name'),
            'rates.*.governorate_en.required' => __('admin.shipping_rates.validation.name_required'),
            'rates.*.governorate_en.string' => __('admin.shipping_rates.validation.invalid'),
            'rates.*.governorate_en.max' => __('admin.shipping_rates.validation.name_max'),
            'rates.*.governorate_en.distinct' => __('admin.shipping_rates.validation.duplicate_name'),
            'rates.*.charge.required' => __('admin.shipping_rates.validation.charge_required'),
            'rates.*.charge.numeric' => __('admin.shipping_rates.validation.charge_invalid'),
            'rates.*.charge.min' => __('admin.shipping_rates.validation.charge_invalid'),
            'rates.*.charge.decimal' => __('admin.shipping_rates.validation.charge_precision'),
            'rates.*.charge.regex' => __('admin.shipping_rates.validation.charge_invalid'),
        ];
    }
}
