<?php

namespace App\Http\Requests\Admin;

use App\Support\ShipmentLifecycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShipmentEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ShipmentLifecycle::STATUSES)],
            'event_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:190'],
            'carrier_cost_actual' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'actual_cod_collected' => ['required_if:status,delivered', 'nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('admin.shipments.validation.status_required'),
            'status.in' => __('admin.shipments.validation.status_invalid'),
            'event_at.required' => __('admin.shipments.validation.event_time_required'),
            'event_at.date' => __('admin.shipments.validation.event_time_invalid'),
            'description.max' => __('admin.shipments.validation.description_max'),
            'location.max' => __('admin.shipments.validation.location_max'),
            'carrier_cost_actual.numeric' => __('admin.shipments.validation.amount_invalid'),
            'carrier_cost_actual.min' => __('admin.shipments.validation.amount_invalid'),
            'carrier_cost_actual.regex' => __('admin.shipments.validation.amount_invalid'),
            'actual_cod_collected.required_if' => __('admin.shipments.validation.actual_cod_required'),
            'actual_cod_collected.numeric' => __('admin.shipments.validation.amount_invalid'),
            'actual_cod_collected.min' => __('admin.shipments.validation.amount_invalid'),
            'actual_cod_collected.regex' => __('admin.shipments.validation.amount_invalid'),
            'idempotency_key.required' => __('admin.shipments.validation.request_invalid'),
            'idempotency_key.uuid' => __('admin.shipments.validation.request_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('event_at') && ! $validator->errors()->has('event_at')
                && Carbon::parse($this->input('event_at'), 'Africa/Cairo')->greaterThan(now('Africa/Cairo'))) {
                $validator->errors()->add('event_at', __('admin.shipments.validation.event_time_future'));
            }
            if ($this->input('status') !== 'delivered' && $this->filled('actual_cod_collected')) {
                $validator->errors()->add('actual_cod_collected', __('admin.shipments.validation.actual_cod_only_when_delivered'));
            }
        });
    }
}
