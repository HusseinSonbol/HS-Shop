<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('store.tickets.validation.body_required'),
            'body.max' => __('store.tickets.validation.body_max'),
            'attachments.array' => __('store.tickets.validation.attachments_invalid'),
            'attachments.max' => __('store.tickets.validation.attachments_invalid'),
            'attachments.*.mimes' => __('store.tickets.validation.attachments_invalid'),
            'attachments.*.max' => __('store.tickets.validation.attachments_invalid'),
        ];
    }
}
