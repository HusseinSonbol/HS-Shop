<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $staffMember = $this->route('user');
        $staffMember = $staffMember instanceof User ? $staffMember : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($staffMember?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$staffMember ? 'nullable' : 'required', 'string', 'min:9', 'max:1024'],
            'role' => ['required', 'string', Rule::exists('roles', 'code')->whereIn('code', ['super_admin', 'manager', 'operator'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('admin.staff.validation.name_required'),
            'name.string' => __('admin.staff.validation.name_invalid'),
            'name.max' => __('admin.staff.validation.name_max'),
            'email.required' => __('admin.staff.validation.email_required'),
            'email.string' => __('admin.staff.validation.email_invalid'),
            'email.email' => __('admin.staff.validation.email_invalid'),
            'email.max' => __('admin.staff.validation.email_max'),
            'email.unique' => __('admin.staff.validation.email_unique'),
            'phone.string' => __('admin.staff.validation.phone_invalid'),
            'phone.max' => __('admin.staff.validation.phone_max'),
            'password.required' => __('admin.staff.validation.password_required'),
            'password.string' => __('admin.staff.validation.password_invalid'),
            'password.min' => __('admin.staff.validation.password_min'),
            'password.max' => __('admin.staff.validation.password_max'),
            'role.required' => __('admin.staff.validation.role_required'),
            'role.string' => __('admin.staff.validation.role_invalid'),
            'role.exists' => __('admin.staff.validation.role_invalid'),
            'is_active.required' => __('admin.staff.validation.status_required'),
            'is_active.boolean' => __('admin.staff.validation.status_invalid'),
        ];
    }
}
