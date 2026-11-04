<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160']];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('admin.shipping_companies.validation.name_required'),
            'name.string' => __('admin.shipping_companies.validation.name_invalid'),
            'name.max' => __('admin.shipping_companies.validation.name_max'),
        ];
    }
}
