<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentProofUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.required' => __('admin.social_orders.validation.proof_required'),
            'payment_proof.image' => __('admin.social_orders.validation.proof_invalid'),
            'payment_proof.mimes' => __('admin.social_orders.validation.proof_invalid'),
            'payment_proof.max' => __('admin.social_orders.validation.proof_too_large'),
            'idempotency_key.required' => __('admin.social_orders.validation.proof_upload_invalid'),
            'idempotency_key.uuid' => __('admin.social_orders.validation.proof_upload_invalid'),
        ];
    }
}
