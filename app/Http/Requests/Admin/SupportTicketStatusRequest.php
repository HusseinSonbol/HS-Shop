<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('support_tickets.manage') === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('admin.support_tickets.validation.status_required'),
            'status.in' => __('admin.support_tickets.validation.status_invalid'),
            'note.max' => __('admin.support_tickets.validation.note_max'),
        ];
    }
}
