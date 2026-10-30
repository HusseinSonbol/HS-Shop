<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeedbackDecisionRequest;
use App\Http\Requests\Admin\FeedbackVisibilityRequest;
use App\Http\Requests\Admin\ReviewDecisionRequest;
use App\Http\Requests\Admin\ReviewVisibilityRequest;
use App\Models\CustomerFeedback;
use App\Models\ProductReview;
use App\Services\Admin\ReviewModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public function reviews(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', 'in:pending,approved,rejected,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'scope.in' => __('admin.reviews.validation.filter_invalid'),
            'q.max' => __('admin.reviews.validation.search_invalid'),
        ]);
        $reviews = ProductReview::query()
            ->when(($filters['scope'] ?? 'pending') !== 'all', fn ($query) => $query->where('moderation_status', $filters['scope'] ?? 'pending'))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(function ($search) use ($term): void {
                    $search->where('comment', 'like', $term)
                        ->orWhereHas('product', fn ($product) => $product->withTrashed()->whereHas('translations', fn ($translation) => $translation->where('name', 'like', $term)))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('display_name', 'like', $term));
                });
            })
            ->with([
                'product' => fn ($product) => $product->withTrashed()->with('translations'),
                'customer' => fn ($customer) => $customer->withTrashed(),
            ])->orderByRaw("CASE WHEN moderation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')->paginate(20)->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews, 'filters' => $filters + ['scope' => 'pending']]);
    }

    public function decideReview(ReviewDecisionRequest $request, ProductReview $review, ReviewModerationService $moderation): RedirectResponse
    {
        $moderation->decideReview($review, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.reviews.index', ['scope' => 'all'])->with('status', __('admin.reviews.moderated'));
    }

    public function reviewVisibility(ReviewVisibilityRequest $request, ProductReview $review, ReviewModerationService $moderation): RedirectResponse
    {
        $moderation->setReviewVisibility($review, $request->validated('visibility_status'), (int) $request->user()->id);

        return redirect()->route('admin.reviews.index', ['scope' => 'all'])->with('status', __('admin.reviews.visibility_updated'));
    }

    public function feedback(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', 'in:pending,approved,rejected,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'scope.in' => __('admin.feedback.validation.filter_invalid'),
            'q.max' => __('admin.feedback.validation.search_invalid'),
        ]);
        $feedback = CustomerFeedback::query()
            ->when(($filters['scope'] ?? 'pending') !== 'all', fn ($query) => $query->where('moderation_status', $filters['scope'] ?? 'pending'))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($search) => $search->where('feedback_number', 'like', $term)
                    ->orWhere('feedback_text', 'like', $term)
                    ->orWhere('sender_name_snapshot', 'like', $term)
                    ->orWhere('sender_email_snapshot', 'like', $term));
            })
            ->with(['customer' => fn ($customer) => $customer->withTrashed(), 'order'])
            ->orderByRaw("CASE WHEN moderation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')->paginate(20)->withQueryString();

        return view('admin.feedback.index', ['feedbackItems' => $feedback, 'filters' => $filters + ['scope' => 'pending']]);
    }

    public function decideFeedback(FeedbackDecisionRequest $request, CustomerFeedback $feedback, ReviewModerationService $moderation): RedirectResponse
    {
        $moderation->decideFeedback($feedback, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.feedback.index', ['scope' => 'all'])->with('status', __('admin.feedback.moderated'));
    }

    public function feedbackVisibility(FeedbackVisibilityRequest $request, CustomerFeedback $feedback, ReviewModerationService $moderation): RedirectResponse
    {
        $moderation->setFeedbackVisibility(
            $feedback,
            $request->validated('visibility_status'),
            (bool) $request->validated('show_on_homepage'),
            (int) $request->user()->id,
        );

        return redirect()->route('admin.feedback.index', ['scope' => 'all'])->with('status', __('admin.feedback.visibility_updated'));
    }
}
