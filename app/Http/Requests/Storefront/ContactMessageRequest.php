<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:220'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sender_name.required' => __('store.contact.validation.name_required'),
            'sender_name.max' => __('store.contact.validation.name_max'),
            'email.required' => __('store.contact.validation.email_required'),
            'email.email' => __('store.contact.validation.email_invalid'),
            'email.max' => __('store.contact.validation.email_max'),
            'phone.max' => __('store.contact.validation.phone_invalid'),
            'subject.max' => __('store.contact.validation.subject_max'),
            'message.required' => __('store.contact.validation.message_required'),
            'message.max' => __('store.contact.validation.message_max'),
            'website.max' => __('store.contact.validation.invalid'),
        ];
    }
}
