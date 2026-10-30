<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('suppliers.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplier = $supplier instanceof Supplier ? $supplier : null;

        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'supplier_type' => ['required', Rule::in(['factory', 'showroom'])],
            'name' => ['required', 'string', 'max:190'],
            'owner_name' => ['nullable', 'string', 'max:160'],
            'owner_phone' => ['nullable', 'string', 'max:32'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'array'],
            'address.governorate' => ['nullable', 'string', 'max:120'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.district' => ['nullable', 'string', 'max:120'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.building' => ['nullable', 'string', 'max:80'],
            'address.floor' => ['nullable', 'string', 'max:80'],
            'address.landmark' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => __('admin.suppliers.validation.code_required'),
            'code.regex' => __('admin.suppliers.validation.code_invalid'),
            'code.unique' => __('admin.suppliers.validation.code_unique'),
            'supplier_type.required' => __('admin.suppliers.validation.type_required'),
            'supplier_type.in' => __('admin.suppliers.validation.type_invalid'),
            'name.required' => __('admin.suppliers.validation.name_required'),
            'name.max' => __('admin.suppliers.validation.name_max'),
            'email.email' => __('admin.suppliers.validation.email_invalid'),
            'address.governorate.max' => __('admin.suppliers.validation.address_field_max'),
            'address.city.max' => __('admin.suppliers.validation.address_field_max'),
            'address.district.max' => __('admin.suppliers.validation.address_field_max'),
            'address.street.max' => __('admin.suppliers.validation.address_field_max'),
            'address.building.max' => __('admin.suppliers.validation.address_field_max'),
            'address.floor.max' => __('admin.suppliers.validation.address_field_max'),
            'address.landmark.max' => __('admin.suppliers.validation.address_field_max'),
            'address.postal_code.max' => __('admin.suppliers.validation.address_field_max'),
            'is_active.required' => __('admin.suppliers.validation.status_required'),
            'is_active.boolean' => __('admin.suppliers.validation.status_invalid'),
        ];
    }
}
