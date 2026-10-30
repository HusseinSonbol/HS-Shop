<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizePhone($this->input('phone'))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers.manage') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\\+?[0-9][0-9\\s().-]{6,31}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'display_name.required' => __('admin.customers.validation.name_required'),
            'display_name.string' => __('admin.customers.validation.name_invalid'),
            'display_name.max' => __('admin.customers.validation.name_max'),
            'phone.required' => __('admin.customers.validation.phone_required'),
            'phone.string' => __('admin.customers.validation.phone_invalid'),
            'phone.max' => __('admin.customers.validation.phone_max'),
            'phone.regex' => __('admin.customers.validation.phone_invalid'),
            'email.email' => __('admin.customers.validation.email_invalid'),
            'email.string' => __('admin.customers.validation.email_invalid'),
            'email.max' => __('admin.customers.validation.email_max'),
            'notes.string' => __('admin.customers.validation.notes_invalid'),
            'notes.max' => __('admin.customers.validation.notes_max'),
        ];
    }

    private function normalizePhone(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        return Str::of($phone)
            ->replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])
            ->trim()
            ->toString();
    }
}
