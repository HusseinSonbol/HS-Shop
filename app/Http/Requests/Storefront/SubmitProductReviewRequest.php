<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_item_id.required' => __('store.reviews.validation.order_required'),
            'rating.required' => __('store.reviews.validation.rating_required'),
            'rating.between' => __('store.reviews.validation.rating_invalid'),
            'comment.max' => __('store.reviews.validation.comment_max'),
        ];
    }
}
