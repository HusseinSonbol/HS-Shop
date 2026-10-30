<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AboutFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('faqs.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.ar' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.ar.question' => ['required', 'string', 'max:500'],
            'translations.ar.answer' => ['required', 'string', 'max:10000'],
            'translations.en.question' => ['required', 'string', 'max:500'],
            'translations.en.answer' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort_order.required' => __('admin.about_faqs.validation.order'), 'sort_order.integer' => __('admin.about_faqs.validation.order'), 'sort_order.min' => __('admin.about_faqs.validation.order'), 'sort_order.max' => __('admin.about_faqs.validation.order'),
            'is_active.required' => __('admin.about_faqs.validation.invalid'), 'is_active.boolean' => __('admin.about_faqs.validation.invalid'),
            'translations.required' => __('admin.about_faqs.validation.required'), 'translations.array' => __('admin.about_faqs.validation.required'),
            'translations.ar.required' => __('admin.about_faqs.validation.required'), 'translations.en.required' => __('admin.about_faqs.validation.required'),
            'translations.*.question.required' => __('admin.about_faqs.validation.required'), 'translations.*.question.string' => __('admin.about_faqs.validation.invalid'), 'translations.*.question.max' => __('admin.about_faqs.validation.invalid'),
            'translations.*.answer.required' => __('admin.about_faqs.validation.required'), 'translations.*.answer.string' => __('admin.about_faqs.validation.invalid'), 'translations.*.answer.max' => __('admin.about_faqs.validation.invalid'),
        ];
    }
}
