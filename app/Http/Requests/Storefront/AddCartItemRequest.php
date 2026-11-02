<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'variant_id.required' => __('store.cart.validation.variant_required'),
            'variant_id.exists' => __('store.cart.validation.variant_invalid'),
            'quantity.required' => __('store.cart.validation.quantity_required'),
            'quantity.numeric' => __('store.cart.validation.quantity_invalid'),
            'quantity.gt' => __('store.cart.validation.quantity_invalid'),
            'quantity.regex' => __('store.cart.validation.quantity_max'),
            'quantity.decimal' => __('store.cart.validation.quantity_precision'),
        ];
    }
}
