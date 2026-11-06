<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCustomerFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['required', 'string', 'max:160'],
            'feedback_text' => ['required', 'string', 'min:5', 'max:3000'],
            'website' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'sender_name.required' => __('store.reviews.validation.name_required'),
            'sender_name.max' => __('store.reviews.validation.name_max'),
            'feedback_text.required' => __('store.reviews.validation.feedback_required'),
            'feedback_text.min' => __('store.reviews.validation.feedback_min'),
            'feedback_text.max' => __('store.reviews.validation.feedback_max'),
        ];
    }
}
