<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('support_tickets.manage') === true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(['public', 'internal'])],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('admin.support_tickets.validation.body_required'),
            'body.max' => __('admin.support_tickets.validation.body_max'),
            'visibility.required' => __('admin.support_tickets.validation.visibility_required'),
            'visibility.in' => __('admin.support_tickets.validation.visibility_invalid'),
            'attachments.array' => __('admin.support_tickets.validation.attachments_invalid'),
            'attachments.max' => __('admin.support_tickets.validation.attachments_invalid'),
            'attachments.*.mimes' => __('admin.support_tickets.validation.attachments_invalid'),
            'attachments.*.max' => __('admin.support_tickets.validation.attachments_invalid'),
        ];
    }
}
