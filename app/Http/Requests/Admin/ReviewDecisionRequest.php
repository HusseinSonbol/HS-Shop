<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reviews.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'moderation_status' => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'moderation_status.required' => __('admin.reviews.validation.status_required'),
            'moderation_status.in' => __('admin.reviews.validation.status_invalid'),
            'rejection_reason.string' => __('admin.reviews.validation.reason_invalid'),
            'rejection_reason.max' => __('admin.reviews.validation.reason_invalid'),
        ];
    }
}
