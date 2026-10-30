<?php

namespace App\Http\Requests\Admin;

use App\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('homepage.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', Rule::in(['home_hero', 'home_promo_top', 'home_promo_middle'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'target_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.ar' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.ar.title' => ['nullable', 'string', 'max:255'],
            'translations.en.title' => ['nullable', 'string', 'max:255'],
            'translations.ar.alt_text' => ['required', 'string', 'max:255'],
            'translations.en.alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->route('banner') instanceof Banner && ! $this->hasFile('image')) {
                $validator->errors()->add('image', __('admin.homepage.validation.image_required'));
            }

            $target = trim((string) $this->input('target_url', ''));
            $normalizedTarget = str_replace('\\', '/', $target);
            if ($target !== '' && ! str_starts_with($target, '/') && ! filter_var($target, FILTER_VALIDATE_URL)) {
                $validator->errors()->add('target_url', __('admin.homepage.validation.target_invalid'));
            } elseif (str_starts_with($normalizedTarget, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) && ! preg_match('/^https?:\/\//i', $target)) {
                $validator->errors()->add('target_url', __('admin.homepage.validation.target_invalid'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'placement.required' => __('admin.homepage.validation.required'), 'placement.in' => __('admin.homepage.validation.invalid'),
            'image.image' => __('admin.homepage.validation.image_format'), 'image.mimes' => __('admin.homepage.validation.image_format'), 'image.max' => __('admin.homepage.validation.image_format'),
            'target_url.string' => __('admin.homepage.validation.invalid'), 'target_url.max' => __('admin.homepage.validation.invalid'), 'sort_order.required' => __('admin.homepage.validation.order'), 'sort_order.integer' => __('admin.homepage.validation.integer'), 'sort_order.min' => __('admin.homepage.validation.order'), 'sort_order.max' => __('admin.homepage.validation.order'),
            'is_active.required' => __('admin.homepage.validation.required'), 'is_active.boolean' => __('admin.homepage.validation.invalid'),
            'translations.required' => __('admin.homepage.validation.translations'), 'translations.array' => __('admin.homepage.validation.translations'),
            'translations.ar.required' => __('admin.homepage.validation.translations'), 'translations.en.required' => __('admin.homepage.validation.translations'),
            'translations.ar.alt_text.required' => __('admin.homepage.validation.alt_required'), 'translations.en.alt_text.required' => __('admin.homepage.validation.alt_required'),
            'translations.*.alt_text.max' => __('admin.homepage.validation.invalid'),
            'translations.*.title.max' => __('admin.homepage.validation.invalid'),
        ];
    }
}
