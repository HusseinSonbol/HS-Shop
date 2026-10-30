<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('homepage.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('status', 'published')->where('store_availability_status', 'available')],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => __('admin.homepage.validation.product_required'),
            'product_id.integer' => __('admin.homepage.validation.product_required'),
            'product_id.exists' => __('admin.homepage.validation.product_required'),
        ];
    }
}
