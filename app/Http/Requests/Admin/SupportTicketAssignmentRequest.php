<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('support_tickets.manage') === true;
    }

    public function rules(): array
    {
        return ['assigned_to' => ['nullable', 'integer', 'exists:users,id']];
    }

    public function messages(): array
    {
        return ['assigned_to.exists' => __('admin.support_tickets.validation.assignee_invalid')];
    }
}
