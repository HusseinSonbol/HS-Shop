<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\Admin\CategoryManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('catalog.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $category = $this->route('category');
        $category = $category instanceof Category ? $category : null;

        return [
            'slug' => [
                'required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true)),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.ar' => ['required', 'array'],
            'translations.ar.name' => ['required', 'string', 'max:190'],
            'translations.ar.description' => ['nullable', 'string', 'max:3000'],
            'translations.en' => ['required', 'array'],
            'translations.en.name' => ['required', 'string', 'max:190'],
            'translations.en.description' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.required' => __('admin.categories.validation.slug_required'),
            'slug.string' => __('admin.categories.validation.slug_invalid'),
            'slug.max' => __('admin.categories.validation.slug_max'),
            'slug.regex' => __('admin.categories.validation.slug_format'),
            'slug.unique' => __('admin.categories.validation.slug_unique'),
            'parent_id.integer' => __('admin.categories.validation.parent_invalid'),
            'parent_id.exists' => __('admin.categories.validation.parent_invalid'),
            'sort_order.required' => __('admin.categories.validation.sort_required'),
            'sort_order.integer' => __('admin.categories.validation.sort_invalid'),
            'sort_order.min' => __('admin.categories.validation.sort_invalid'),
            'sort_order.max' => __('admin.categories.validation.sort_invalid'),
            'is_active.required' => __('admin.categories.validation.status_required'),
            'is_active.boolean' => __('admin.categories.validation.status_invalid'),
            'translations.required' => __('admin.categories.validation.translations_required'),
            'translations.array' => __('admin.categories.validation.translations_invalid'),
            'translations.ar.required' => __('admin.categories.validation.arabic_required'),
            'translations.ar.array' => __('admin.categories.validation.translations_invalid'),
            'translations.ar.name.required' => __('admin.categories.validation.arabic_name_required'),
            'translations.ar.name.string' => __('admin.categories.validation.name_invalid'),
            'translations.ar.name.max' => __('admin.categories.validation.name_max'),
            'translations.ar.description.string' => __('admin.categories.validation.description_invalid'),
            'translations.ar.description.max' => __('admin.categories.validation.description_max'),
            'translations.en.required' => __('admin.categories.validation.english_required'),
            'translations.en.array' => __('admin.categories.validation.translations_invalid'),
            'translations.en.name.required' => __('admin.categories.validation.english_name_required'),
            'translations.en.name.string' => __('admin.categories.validation.name_invalid'),
            'translations.en.name.max' => __('admin.categories.validation.name_max'),
            'translations.en.description.string' => __('admin.categories.validation.description_invalid'),
            'translations.en.description.max' => __('admin.categories.validation.description_max'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');
            if (! $category instanceof Category || ! is_numeric($parentId) || $validator->errors()->has('parent_id')) {
                return;
            }

            if (app(CategoryManagementService::class)->wouldCreateCycle($category->id, (int) $parentId)) {
                $validator->errors()->add('parent_id', __('admin.categories.validation.cycle'));
            }
        }];
    }
}
