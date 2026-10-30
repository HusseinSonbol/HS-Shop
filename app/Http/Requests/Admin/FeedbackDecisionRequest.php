<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('feedback.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'moderation_status' => ['required', 'in:approved,rejected'],
            'staff_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'moderation_status.required' => __('admin.feedback.validation.status_required'),
            'moderation_status.in' => __('admin.feedback.validation.status_invalid'),
            'staff_note.string' => __('admin.feedback.validation.note_invalid'),
            'staff_note.max' => __('admin.feedback.validation.note_invalid'),
        ];
    }
}
