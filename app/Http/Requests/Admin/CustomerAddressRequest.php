<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CustomerAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge([
                'phone' => Str::of($phone)
                    ->replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])
                    ->trim()
                    ->toString(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\\+?[0-9][0-9\\s().-]{6,31}$/'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'governorate' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'street' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:80'],
            'apartment' => ['nullable', 'string', 'max:80'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'label.string' => __('admin.customers.validation.address_label_invalid'),
            'label.max' => __('admin.customers.validation.address_label_max'),
            'recipient_name.required' => __('admin.customers.validation.recipient_required'),
            'recipient_name.string' => __('admin.customers.validation.recipient_invalid'),
            'recipient_name.max' => __('admin.customers.validation.recipient_max'),
            'phone.required' => __('admin.customers.validation.phone_required'),
            'phone.string' => __('admin.customers.validation.phone_invalid'),
            'phone.regex' => __('admin.customers.validation.phone_invalid'),
            'phone.max' => __('admin.customers.validation.phone_max'),
            'country_code.required' => __('admin.customers.validation.country_code_required'),
            'country_code.string' => __('admin.customers.validation.country_code_invalid'),
            'country_code.size' => __('admin.customers.validation.country_code_invalid'),
            'country_code.regex' => __('admin.customers.validation.country_code_invalid'),
            'governorate.required' => __('admin.customers.validation.governorate_required'),
            'governorate.string' => __('admin.customers.validation.address_field_invalid'),
            'governorate.max' => __('admin.customers.validation.address_field_max'),
            'city.required' => __('admin.customers.validation.city_required'),
            'city.string' => __('admin.customers.validation.address_field_invalid'),
            'city.max' => __('admin.customers.validation.address_field_max'),
            'district.max' => __('admin.customers.validation.address_field_max'),
            'district.string' => __('admin.customers.validation.address_field_invalid'),
            'street.required' => __('admin.customers.validation.street_required'),
            'street.string' => __('admin.customers.validation.address_field_invalid'),
            'street.max' => __('admin.customers.validation.address_field_max'),
            'building.max' => __('admin.customers.validation.address_field_max'),
            'building.string' => __('admin.customers.validation.address_field_invalid'),
            'floor.max' => __('admin.customers.validation.address_field_max'),
            'floor.string' => __('admin.customers.validation.address_field_invalid'),
            'apartment.max' => __('admin.customers.validation.address_field_max'),
            'apartment.string' => __('admin.customers.validation.address_field_invalid'),
            'landmark.string' => __('admin.customers.validation.address_field_invalid'),
            'landmark.max' => __('admin.customers.validation.landmark_max'),
            'postal_code.string' => __('admin.customers.validation.address_field_invalid'),
            'postal_code.max' => __('admin.customers.validation.postal_code_max'),
            'is_default.boolean' => __('admin.customers.validation.default_invalid'),
        ];
    }
}
