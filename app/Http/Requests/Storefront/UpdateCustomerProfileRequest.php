<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCustomerProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\\+?[0-9][0-9\\s().-]{6,31}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('store.customer_account.validation.name_required'),
            'name.string' => __('store.customer_account.validation.name_invalid'),
            'name.max' => __('store.customer_account.validation.name_max'),
            'phone.required' => __('store.customer_account.validation.phone_required'),
            'phone.string' => __('store.customer_account.validation.phone_invalid'),
            'phone.max' => __('store.customer_account.validation.phone_invalid'),
            'phone.regex' => __('store.customer_account.validation.phone_invalid'),
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
