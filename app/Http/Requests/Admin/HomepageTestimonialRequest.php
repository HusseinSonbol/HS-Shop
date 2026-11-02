<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HomepageTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('homepage.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.ar' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.ar.name' => ['required', 'string', 'max:160'],
            'translations.en.name' => ['required', 'string', 'max:160'],
            'translations.ar.message' => ['required', 'string', 'max:3000'],
            'translations.en.message' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.image' => __('admin.homepage.validation.image_format'),
            'image.mimes' => __('admin.homepage.validation.image_format'),
            'image.max' => __('admin.homepage.validation.image_format'),
            'sort_order.required' => __('admin.homepage.validation.order'),
            'sort_order.integer' => __('admin.homepage.validation.integer'),
            'sort_order.min' => __('admin.homepage.validation.order'),
            'sort_order.max' => __('admin.homepage.validation.order'),
            'is_active.required' => __('admin.homepage.validation.required'),
            'is_active.boolean' => __('admin.homepage.validation.invalid'),
            'translations.required' => __('admin.homepage.validation.translations'),
            'translations.*.name.required' => __('admin.homepage.testimonials.validation.name'),
            'translations.*.message.required' => __('admin.homepage.testimonials.validation.message'),
            'translations.*.name.max' => __('admin.homepage.validation.invalid'),
            'translations.*.message.max' => __('admin.homepage.validation.invalid'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->route('testimonialId') && ! $this->hasFile('image')) {
                $validator->errors()->add('image', __('admin.homepage.testimonials.validation.image_required'));
            }
        }];
    }
}
