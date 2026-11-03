<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlaceWebsiteOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $address = $this->input('shipping_address');
        if (is_array($address) && is_string($address['phone'] ?? null)) {
            $address['phone'] = Str::of($address['phone'])
                ->replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])
                ->trim()
                ->toString();
            $this->merge(['shipping_address' => $address]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user('customer');

        return $user !== null
            && $user->is_active
            && ! $user->trashed()
            && $user->customer()->exists();
    }

    public function rules(): array
    {
        $customerId = $this->user('customer')?->customer?->id;

        return [
            'idempotency_key' => ['required', 'uuid'],
            'quote_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'shipping_rate_code' => ['required', 'uuid'],
            'address_source' => ['required', Rule::in(['saved', 'new'])],
            'address_id' => [
                'required_if:address_source,saved',
                'nullable',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(fn ($query) => $query->where('customer_id', $customerId)->whereNull('deleted_at')),
            ],
            'shipping_address' => ['required_if:address_source,new', 'array'],
            'shipping_address.label' => ['nullable', 'string', 'max:80'],
            'shipping_address.recipient_name' => ['required_if:address_source,new', 'nullable', 'string', 'max:160'],
            'shipping_address.phone' => ['required_if:address_source,new', 'nullable', 'string', 'max:32', 'regex:/^\\+?[0-9][0-9\\s().-]{6,31}$/'],
            'shipping_address.city' => ['required_if:address_source,new', 'nullable', 'string', 'max:120'],
            'shipping_address.district' => ['nullable', 'string', 'max:120'],
            'shipping_address.street' => ['required_if:address_source,new', 'nullable', 'string', 'max:255'],
            'shipping_address.building' => ['nullable', 'string', 'max:80'],
            'shipping_address.floor' => ['nullable', 'string', 'max:80'],
            'shipping_address.apartment' => ['nullable', 'string', 'max:80'],
            'shipping_address.landmark' => ['nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'save_address' => ['sometimes', 'boolean'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => __('store.checkout.validation.try_again'),
            'idempotency_key.uuid' => __('store.checkout.validation.try_again'),
            'quote_token.required' => __('store.checkout.validation.review_total'),
            'quote_token.regex' => __('store.checkout.validation.review_total'),
            'shipping_rate_code.required' => __('store.checkout.validation.governorate_required'),
            'shipping_rate_code.uuid' => __('store.checkout.validation.governorate_invalid'),
            'address_source.required' => __('store.checkout.validation.address_source_required'),
            'address_source.in' => __('store.checkout.validation.address_source_required'),
            'address_id.required_if' => __('store.checkout.validation.address_required'),
            'address_id.integer' => __('store.checkout.validation.address_invalid'),
            'address_id.exists' => __('store.checkout.validation.address_invalid'),
            'shipping_address.required_if' => __('store.checkout.validation.address_required'),
            'shipping_address.array' => __('store.checkout.validation.address_invalid'),
            'shipping_address.label.max' => __('store.customer_account.validation.address_label_max'),
            'shipping_address.recipient_name.required_if' => __('store.customer_account.validation.recipient_required'),
            'shipping_address.recipient_name.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.phone.required_if' => __('store.customer_account.validation.phone_required'),
            'shipping_address.phone.max' => __('store.customer_account.validation.phone_invalid'),
            'shipping_address.phone.regex' => __('store.customer_account.validation.phone_invalid'),
            'shipping_address.city.required_if' => __('store.customer_account.validation.city_required'),
            'shipping_address.city.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.district.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.street.required_if' => __('store.customer_account.validation.street_required'),
            'shipping_address.street.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.building.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.floor.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.apartment.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.landmark.max' => __('store.customer_account.validation.address_field_max'),
            'shipping_address.postal_code.max' => __('store.customer_account.validation.address_field_max'),
            'save_address.boolean' => __('store.checkout.validation.save_address_invalid'),
            'customer_note.max' => __('store.checkout.validation.note_max'),
        ];
    }
}
