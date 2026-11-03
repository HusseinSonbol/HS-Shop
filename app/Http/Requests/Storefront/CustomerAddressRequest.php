<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CustomerAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge(['phone' => $this->normalizePhone($phone)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user('customer')?->customer()->exists() ?? false;
    }

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

    public function messages(): array
    {
        return [
            'label.max' => __('store.customer_account.validation.address_label_max'),
            'recipient_name.required' => __('store.customer_account.validation.recipient_required'),
            'recipient_name.string' => __('store.customer_account.validation.address_field_max'),
            'recipient_name.max' => __('store.customer_account.validation.address_field_max'),
            'phone.required' => __('store.customer_account.validation.phone_required'),
            'phone.string' => __('store.customer_account.validation.phone_invalid'),
            'phone.max' => __('store.customer_account.validation.phone_invalid'),
            'phone.regex' => __('store.customer_account.validation.phone_invalid'),
            'country_code.required' => __('store.customer_account.validation.country_required'),
            'country_code.string' => __('store.customer_account.validation.country_invalid'),
            'country_code.size' => __('store.customer_account.validation.country_invalid'),
            'country_code.regex' => __('store.customer_account.validation.country_invalid'),
            'governorate.required' => __('store.customer_account.validation.governorate_required'),
            'governorate.string' => __('store.customer_account.validation.address_field_max'),
            'governorate.max' => __('store.customer_account.validation.address_field_max'),
            'city.required' => __('store.customer_account.validation.city_required'),
            'city.string' => __('store.customer_account.validation.address_field_max'),
            'city.max' => __('store.customer_account.validation.address_field_max'),
            'district.max' => __('store.customer_account.validation.address_field_max'),
            'district.string' => __('store.customer_account.validation.address_field_max'),
            'street.required' => __('store.customer_account.validation.street_required'),
            'street.string' => __('store.customer_account.validation.address_field_max'),
            'street.max' => __('store.customer_account.validation.address_field_max'),
            'building.max' => __('store.customer_account.validation.address_field_max'),
            'building.string' => __('store.customer_account.validation.address_field_max'),
            'floor.max' => __('store.customer_account.validation.address_field_max'),
            'floor.string' => __('store.customer_account.validation.address_field_max'),
            'apartment.max' => __('store.customer_account.validation.address_field_max'),
            'apartment.string' => __('store.customer_account.validation.address_field_max'),
            'landmark.string' => __('store.customer_account.validation.address_field_max'),
            'landmark.max' => __('store.customer_account.validation.address_field_max'),
            'postal_code.string' => __('store.customer_account.validation.address_field_max'),
            'postal_code.max' => __('store.customer_account.validation.address_field_max'),
            'is_default.boolean' => __('store.customer_account.validation.default_invalid'),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return Str::of($phone)
            ->replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])
            ->trim()
            ->toString();
    }
}
