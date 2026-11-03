<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'gt:0', 'regex:/^\d{1,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => __('store.cart.validation.quantity_required'),
            'quantity.integer' => __('store.cart.validation.quantity_invalid'),
            'quantity.gt' => __('store.cart.validation.quantity_invalid'),
            'quantity.regex' => __('store.cart.validation.quantity_max'),
        ];
    }
}
