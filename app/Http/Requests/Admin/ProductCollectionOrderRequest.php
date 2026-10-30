<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductCollectionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('homepage.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.position' => ['required', 'integer', 'distinct', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('admin.homepage.validation.collection_changed'), 'items.array' => __('admin.homepage.validation.collection_changed'),
            'items.*.id.required' => __('admin.homepage.validation.invalid'), 'items.*.id.integer' => __('admin.homepage.validation.invalid'), 'items.*.id.distinct' => __('admin.homepage.validation.duplicate'),
            'items.*.position.required' => __('admin.homepage.validation.order'), 'items.*.position.integer' => __('admin.homepage.validation.integer'), 'items.*.position.distinct' => __('admin.homepage.validation.duplicate'), 'items.*.position.min' => __('admin.homepage.validation.order'), 'items.*.position.max' => __('admin.homepage.validation.order'),
        ];
    }
}
