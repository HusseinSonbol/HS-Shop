<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentProofReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => __('admin.social_orders.validation.review_decision_required'),
            'decision.in' => __('admin.social_orders.validation.review_decision_invalid'),
            'rejection_reason.required_if' => __('admin.social_orders.validation.rejection_reason_required'),
            'rejection_reason.max' => __('admin.social_orders.validation.rejection_reason_too_long'),
        ];
    }
}
