<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertContactToTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('support_tickets.manage') === true
            && $this->user()?->hasPermission('contact_messages.manage') === true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.exists' => __('admin.support_tickets.validation.assignee_invalid'),
            'priority.in' => __('admin.support_tickets.validation.priority_invalid'),
            'category.max' => __('admin.support_tickets.validation.category_max'),
        ];
    }
}
