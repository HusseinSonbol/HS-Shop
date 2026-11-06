<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SubmitProductReviewRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewSubmissionController extends Controller
{
    public function store(SubmitProductReviewRequest $request, string $slug): RedirectResponse
    {
        $user = $request->user('customer');
        $customer = $user?->customer;
        abort_unless($customer instanceof Customer && ! $customer->trashed(), 403);

        $data = $request->validated();

        DB::transaction(function () use ($customer, $data, $slug): void {
            $orderItem = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('order_revision_items', function ($join): void {
                    $join->on('order_revision_items.order_item_id', '=', 'order_items.id')
                        ->on('order_revision_items.order_revision_id', '=', 'orders.applied_revision_id');
                })
                ->join('products', 'products.id', '=', 'order_revision_items.product_id')
                ->where('order_items.id', $data['order_item_id'])
                ->where('orders.customer_id', $customer->id)
                ->where('orders.status', 'received')
                ->where('orders.close_outcome', 'success')
                ->where('products.slug', $slug)
                ->lockForUpdate()
                ->select('order_items.id', 'orders.id as order_id', 'products.id as product_id')
                ->first();

            if (! $orderItem) {
                throw ValidationException::withMessages(['order_item_id' => __('store.reviews.validation.order_ineligible')]);
            }

            $alreadyReviewed = DB::table('product_reviews')
                ->where('order_item_id', $orderItem->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyReviewed) {
                throw ValidationException::withMessages(['order_item_id' => __('store.reviews.validation.already_reviewed')]);
            }

            DB::table('product_reviews')->insert([
                'product_id' => $orderItem->product_id,
                'customer_id' => $customer->id,
                'order_item_id' => $orderItem->id,
                'rating' => $data['rating'],
                'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
                'moderation_status' => 'pending',
                'visibility_status' => 'hidden',
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->to(route('store.product', $slug).'#product-reviews')->with('review_status', __('store.reviews.product_submitted'));
    }
}
