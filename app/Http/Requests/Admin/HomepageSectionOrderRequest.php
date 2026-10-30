<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepageSectionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('homepage.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['required', 'integer', 'distinct', Rule::exists('homepage_sections', 'id')],
            'sections.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sections.*.is_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.required' => __('admin.homepage.validation.sections_changed'), 'sections.array' => __('admin.homepage.validation.sections_changed'),
            'sections.*.id.required' => __('admin.homepage.validation.invalid'), 'sections.*.id.integer' => __('admin.homepage.validation.invalid'), 'sections.*.id.distinct' => __('admin.homepage.validation.duplicate'), 'sections.*.id.exists' => __('admin.homepage.validation.sections_changed'),
            'sections.*.sort_order.required' => __('admin.homepage.validation.order'), 'sections.*.sort_order.integer' => __('admin.homepage.validation.integer'), 'sections.*.sort_order.min' => __('admin.homepage.validation.order'), 'sections.*.sort_order.max' => __('admin.homepage.validation.order'),
            'sections.*.is_enabled.required' => __('admin.homepage.validation.required'), 'sections.*.is_enabled.boolean' => __('admin.homepage.validation.invalid'),
        ];
    }
}
