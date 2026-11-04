<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'channel' => ['required', Rule::in(['facebook', 'instagram'])],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'shipping_rate_code' => ['required', 'uuid'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:160'],
            'shipping_address.phone' => ['required', 'string', 'max:32'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.district' => ['nullable', 'string', 'max:120'],
            'shipping_address.street' => ['required', 'string', 'max:255'],
            'shipping_address.building' => ['nullable', 'string', 'max:80'],
            'shipping_address.floor' => ['nullable', 'string', 'max:80'],
            'shipping_address.apartment' => ['nullable', 'string', 'max:80'],
            'shipping_address.landmark' => ['nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', Rule::in(['cod', 'instapay'])],
            'payment_proof' => ['required_if:payment_method,instapay', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => __('admin.social_orders.validation.customer_required'),
            'customer_id.integer' => __('admin.social_orders.validation.customer_invalid'),
            'customer_id.exists' => __('admin.social_orders.validation.customer_invalid'),
            'channel.required' => __('admin.social_orders.validation.channel_required'),
            'channel.in' => __('admin.social_orders.validation.channel_invalid'),
            'items.required' => __('admin.social_orders.validation.items_required'),
            'items.min' => __('admin.social_orders.validation.items_required'),
            'items.max' => __('admin.social_orders.validation.items_too_many'),
            'items.*.variant_id.required' => __('admin.social_orders.validation.variant_required'),
            'items.*.variant_id.integer' => __('admin.social_orders.validation.variant_invalid'),
            'items.*.variant_id.distinct' => __('admin.social_orders.validation.variant_duplicate'),
            'items.*.variant_id.exists' => __('admin.social_orders.validation.variant_invalid'),
            'items.*.quantity.required' => __('admin.social_orders.validation.quantity_required'),
            'items.*.quantity.integer' => __('admin.social_orders.validation.quantity_invalid'),
            'items.*.quantity.min' => __('admin.social_orders.validation.quantity_invalid'),
            'items.*.quantity.max' => __('admin.social_orders.validation.quantity_invalid'),
            'shipping_address.required' => __('admin.social_orders.validation.address_required'),
            'shipping_address.recipient_name.required' => __('admin.social_orders.validation.recipient_required'),
            'shipping_address.recipient_name.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.phone.required' => __('admin.social_orders.validation.phone_required'),
            'shipping_address.phone.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.city.required' => __('admin.social_orders.validation.city_required'),
            'shipping_address.city.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.street.required' => __('admin.social_orders.validation.street_required'),
            'shipping_address.street.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.district.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.building.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.floor.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.apartment.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.landmark.max' => __('admin.social_orders.validation.address_invalid'),
            'shipping_address.postal_code.max' => __('admin.social_orders.validation.address_invalid'),
            'source_reference.max' => __('admin.social_orders.validation.reference_too_long'),
            'customer_note.max' => __('admin.social_orders.validation.note_too_long'),
            'shipping_rate_code.required' => __('admin.social_orders.validation.shipping_required'),
            'shipping_rate_code.uuid' => __('admin.social_orders.validation.shipping_invalid'),
            'payment_method.required' => __('admin.social_orders.validation.payment_method_required'),
            'payment_method.in' => __('admin.social_orders.validation.payment_method_invalid'),
            'payment_proof.required_if' => __('admin.social_orders.validation.proof_required'),
            'payment_proof.image' => __('admin.social_orders.validation.proof_invalid'),
            'payment_proof.mimes' => __('admin.social_orders.validation.proof_invalid'),
            'payment_proof.max' => __('admin.social_orders.validation.proof_too_large'),
        ];
    }
}
