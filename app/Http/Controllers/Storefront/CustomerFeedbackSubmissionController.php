<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SubmitCustomerFeedbackRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerFeedbackSubmissionController extends Controller
{
    public function store(SubmitCustomerFeedbackRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('store.home', [], 303)->with('feedback_status', __('store.reviews.feedback_submitted'));
        }

        $user = $request->user('customer');
        $customerId = $user?->customer?->id;
        $data = $request->validated();

        DB::table('customer_feedback')->insert([
            'feedback_number' => 'FB-'.Str::upper((string) Str::ulid()),
            'customer_id' => $customerId,
            'sender_name_snapshot' => trim($data['sender_name']),
            'feedback_text' => trim($data['feedback_text']),
            'moderation_status' => 'pending',
            'visibility_status' => 'hidden',
            'show_on_homepage' => false,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('store.home', [], 303)->with('feedback_status', __('store.reviews.feedback_submitted'));
    }
}
