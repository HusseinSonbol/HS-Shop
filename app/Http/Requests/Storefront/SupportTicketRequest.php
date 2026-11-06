<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:220'],
            'body' => ['required', 'string', 'max:5000'],
            'order_public_id' => ['nullable', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => __('store.tickets.validation.subject_required'),
            'subject.max' => __('store.tickets.validation.subject_max'),
            'order_public_id.uuid' => __('store.tickets.order_invalid'),
            'body.required' => __('store.tickets.validation.body_required'),
            'body.max' => __('store.tickets.validation.body_max'),
            'attachments.array' => __('store.tickets.validation.attachments_invalid'),
            'attachments.max' => __('store.tickets.validation.attachments_invalid'),
            'attachments.*.mimes' => __('store.tickets.validation.attachments_invalid'),
            'attachments.*.max' => __('store.tickets.validation.attachments_invalid'),
        ];
    }
}
