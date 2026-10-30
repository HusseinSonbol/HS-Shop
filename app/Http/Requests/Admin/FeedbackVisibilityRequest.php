<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FeedbackVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('feedback.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'visibility_status' => ['required', 'in:hidden,visible'],
            'show_on_homepage' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'visibility_status.required' => __('admin.feedback.validation.visibility_required'),
            'visibility_status.in' => __('admin.feedback.validation.visibility_invalid'),
            'show_on_homepage.required' => __('admin.feedback.validation.homepage_required'),
            'show_on_homepage.boolean' => __('admin.feedback.validation.homepage_invalid'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $feedback = $this->route('feedback');
            $showOnHome = filter_var($this->input('show_on_homepage'), FILTER_VALIDATE_BOOLEAN);
            if ($showOnHome && $feedback instanceof CustomerFeedback
                && ($feedback->moderation_status !== 'approved' || $this->input('visibility_status') !== 'visible')) {
                $validator->errors()->add('show_on_homepage', __('admin.feedback.validation.approval_and_visibility_required'));
            }
        }];
    }
}
