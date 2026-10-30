<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reviews.moderate') ?? false;
    }

    public function rules(): array
    {
        return ['visibility_status' => ['required', 'in:hidden,visible']];
    }

    public function messages(): array
    {
        return [
            'visibility_status.required' => __('admin.reviews.validation.visibility_required'),
            'visibility_status.in' => __('admin.reviews.validation.visibility_invalid'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $review = $this->route('review');
            if ($this->input('visibility_status') === 'visible'
                && $review instanceof ProductReview && $review->moderation_status !== 'approved') {
                $validator->errors()->add('visibility_status', __('admin.reviews.validation.approval_required'));
            }
        }];
    }
}
