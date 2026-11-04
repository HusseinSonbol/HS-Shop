<?php

namespace App\Http\Requests\Admin;

use App\Support\OrderLifecycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OrderEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(['follow_up_update', 'status_changed', 'closed', 'reopened'])],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'new_status' => ['required_if:event_type,status_changed', 'nullable', Rule::in(['confirmed', 'shipped', 'received'])],
            'outcome' => ['required_if:event_type,closed', 'nullable', Rule::in(['failure'])],
            'reason_code' => ['required_if:outcome,failure', 'nullable', Rule::in(OrderLifecycle::FAILURE_REASONS)],
            'reason_details' => ['required_if:reason_code,other', 'nullable', 'string', 'max:500'],
            'customer_received' => ['required_if:new_status,received', 'accepted_if:new_status,received'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_type.required' => __('admin.orders.validation.event_required'),
            'event_type.in' => __('admin.orders.validation.event_invalid'),
            'assigned_user_id.exists' => __('admin.orders.validation.assignee_invalid'),
            'assigned_user_id.integer' => __('admin.orders.validation.assignee_invalid'),
            'next_follow_up_at.date' => __('admin.orders.validation.follow_up_invalid'),
            'note.string' => __('admin.orders.validation.note_invalid'),
            'note.max' => __('admin.orders.validation.note_max'),
            'new_status.required_if' => __('admin.orders.validation.status_required'),
            'new_status.in' => __('admin.orders.validation.status_invalid'),
            'outcome.in' => __('admin.orders.validation.outcome_invalid'),
            'reason_code.required_if' => __('admin.orders.validation.reason_required'),
            'reason_code.in' => __('admin.orders.validation.reason_invalid'),
            'reason_details.required_if' => __('admin.orders.validation.reason_details_required'),
            'reason_details.string' => __('admin.orders.validation.reason_details_invalid'),
            'reason_details.max' => __('admin.orders.validation.reason_details_max'),
            'customer_received.required_if' => __('admin.orders.validation.customer_receipt_required'),
            'customer_received.accepted_if' => __('admin.orders.validation.customer_receipt_required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('next_follow_up_at');
            if ($this->input('event_type') === 'follow_up_update' && ! $this->hasOptionalFollowUpChange()) {
                $validator->errors()->add('event_type', __('admin.orders.validation.follow_up_change_required'));
            }

            if ($this->input('event_type') === 'follow_up_update' && is_string($value) && $value !== ''
                && ! $validator->errors()->has('next_follow_up_at')
                && ! Carbon::parse($value, 'Africa/Cairo')->greaterThan(now('Africa/Cairo'))) {
                $validator->errors()->add('next_follow_up_at', __('admin.orders.validation.follow_up_future'));
            }
        });
    }

    private function hasOptionalFollowUpChange(): bool
    {
        return filled($this->input('assigned_user_id'))
            || filled($this->input('next_follow_up_at'))
            || (is_string($this->input('note')) && trim($this->input('note')) !== '');
    }
}
