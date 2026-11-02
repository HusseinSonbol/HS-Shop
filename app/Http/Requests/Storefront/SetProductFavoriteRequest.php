<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SetProductFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_favorite' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return [
            'is_favorite.required' => __('store.favorites.invalid_action'),
            'is_favorite.boolean' => __('store.favorites.invalid_action'),
        ];
    }
}
