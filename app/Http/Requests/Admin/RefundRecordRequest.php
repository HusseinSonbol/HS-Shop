<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('refunds.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => __('admin.returns.validation.refund_amount_required'), 'amount.numeric' => __('admin.returns.validation.amount_invalid'),
            'amount.gt' => __('admin.returns.validation.amount_invalid'), 'amount.regex' => __('admin.returns.validation.amount_invalid'),
            'reason.required' => __('admin.returns.validation.refund_reason_required'), 'reason.min' => __('admin.returns.validation.refund_reason_required'),
            'reason.max' => __('admin.returns.validation.refund_reason_max'),
        ];
    }
}
