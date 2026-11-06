<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_one_pending_review_for_their_received_product(): void
    {
        [$user, $customerId] = $this->createCustomer();
        [$productId, $slug] = $this->createProduct();
        $orderItemId = $this->createReceivedOrderItem($customerId, $productId);

        $this->actingAs($user, 'customer')->post(route('store.product-reviews.store', $slug), [
            'order_item_id' => $orderItemId,
            'rating' => 5,
            'comment' => 'Excellent quality.',
        ])->assertRedirect(route('store.product', $slug).'#product-reviews')
            ->assertSessionHas('review_status', __('store.reviews.product_submitted'));

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $productId,
            'customer_id' => $customerId,
            'order_item_id' => $orderItemId,
            'rating' => 5,
            'comment' => 'Excellent quality.',
            'moderation_status' => 'pending',
            'visibility_status' => 'hidden',
        ]);
    }

    public function test_guest_and_unreceived_or_other_customer_order_items_cannot_submit_product_reviews(): void
    {
        [, $ownerId] = $this->createCustomer();
        [$productId, $slug] = $this->createProduct();
        $ownerItemId = $this->createReceivedOrderItem($ownerId, $productId);

        $this->post(route('store.product-reviews.store', $slug), [
            'order_item_id' => $ownerItemId, 'rating' => 4,
        ])->assertRedirect(route('store.account.login'));

        [$otherUser] = $this->createCustomer();
        $this->actingAs($otherUser, 'customer')->post(route('store.product-reviews.store', $slug), [
            'order_item_id' => $ownerItemId, 'rating' => 4,
        ])->assertSessionHasErrors('order_item_id');

        [$ownerUser] = $this->createCustomer();
        $unreceivedCustomerId = (int) DB::table('customers')->where('user_id', $ownerUser->id)->value('id');
        $unreceivedItemId = $this->createReceivedOrderItem($unreceivedCustomerId, $productId);
        DB::table('orders')->where('id', DB::table('order_items')->where('id', $unreceivedItemId)->value('order_id'))->update(['status' => 'confirmed']);
        $this->actingAs($ownerUser, 'customer')->post(route('store.product-reviews.store', $slug), [
            'order_item_id' => $unreceivedItemId, 'rating' => 4,
        ])->assertSessionHasErrors('order_item_id');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_product_review_cannot_be_submitted_twice_for_the_same_order_item(): void
    {
        [$user, $customerId] = $this->createCustomer();
        [$productId, $slug] = $this->createProduct();
        $orderItemId = $this->createReceivedOrderItem($customerId, $productId);
        $this->actingAs($user, 'customer');

        $payload = ['order_item_id' => $orderItemId, 'rating' => 3];
        $this->post(route('store.product-reviews.store', $slug), $payload)->assertRedirect();
        $this->from(route('store.product', $slug))->post(route('store.product-reviews.store', $slug), $payload)->assertSessionHasErrors('order_item_id');
        $this->assertDatabaseCount('product_reviews', 1);
    }

    public function test_guest_feedback_is_private_pending_moderation_and_keeps_submitted_name(): void
    {
        $this->from(route('store.home'))->post(route('store.customer-feedback.store'), [
            'sender_name' => 'Guest Name',
            'feedback_text' => 'The service was kind and helpful.',
        ])->assertRedirect(route('store.home'))
            ->assertSessionHas('feedback_status', __('store.reviews.feedback_submitted'));

        $this->assertDatabaseHas('customer_feedback', [
            'sender_name_snapshot' => 'Guest Name',
            'feedback_text' => 'The service was kind and helpful.',
            'moderation_status' => 'pending',
            'visibility_status' => 'hidden',
            'show_on_homepage' => 0,
        ]);

        $feedbackId = (int) DB::table('customer_feedback')->where('sender_name_snapshot', 'Guest Name')->value('id');
        $this->get(route('store.home'))->assertDontSee('Guest Name')->assertDontSee('The service was kind and helpful.');
        DB::table('customer_feedback')->where('id', $feedbackId)->update([
            'moderation_status' => 'approved', 'visibility_status' => 'visible',
            'show_on_homepage' => true, 'reviewed_at' => now(),
        ]);
        $this->get(route('store.home'))->assertSee('Guest Name')->assertSee('The service was kind and helpful.');
    }

    public function test_filled_feedback_honeypot_does_not_store_a_submission(): void
    {
        $this->post(route('store.customer-feedback.store'), [
            'sender_name' => 'Bot', 'feedback_text' => 'Automated spam message.', 'website' => 'https://spam.test',
        ])->assertRedirect(route('store.home'))->assertSessionHas('feedback_status');

        $this->assertDatabaseCount('customer_feedback', 0);
    }

    public function test_invalid_rating_and_feedback_are_rejected_with_localized_field_errors(): void
    {
        [$user] = $this->createCustomer();
        [, $slug] = $this->createProduct();
        $this->actingAs($user, 'customer')->post(route('store.product-reviews.store', $slug), [
            'order_item_id' => 1, 'rating' => 6,
        ])->assertSessionHasErrors('rating');

        $this->post(route('store.customer-feedback.store'), [
            'sender_name' => '', 'feedback_text' => 'x',
        ])->assertSessionHasErrors(['sender_name', 'feedback_text']);
    }

    /** @return array{User, int} */
    private function createCustomer(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $customerId = (int) DB::table('customers')->insertGetId([
            'user_id' => $user->id,
            'customer_number' => 'CUS-'.Str::upper((string) Str::ulid()),
            'display_name' => 'Review Customer',
            'phone' => '01012345678',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $customerId];
    }

    /** @return array{int, string} */
    private function createProduct(): array
    {
        $suffix = Str::lower(Str::random(8));
        $id = (int) DB::table('products')->insertGetId([
            'product_type' => 'simple',
            'sku' => 'REVIEW-'.Str::upper($suffix),
            'slug' => 'review-'.$suffix,
            'status' => 'published',
            'store_availability_status' => 'available',
            'requires_shipping' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$id, 'review-'.$suffix];
    }

    private function createReceivedOrderItem(int $customerId, int $productId): int
    {
        $orderId = (int) DB::table('orders')->insertGetId([
            'order_number' => 'HS-'.Str::upper(Str::random(10)),
            'public_id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'channel' => 'website',
            'status' => 'received',
            'close_outcome' => 'success',
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'customer_shipping_charge' => 0,
            'grand_total' => 100,
            'placed_at' => now()->subDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderItemId = (int) DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'line_key' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
        $revisionId = (int) DB::table('order_revisions')->insertGetId([
            'order_id' => $orderId,
            'revision_no' => 1,
            'revision_type' => 'initial',
            'status' => 'applied',
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'customer_shipping_charge' => 0,
            'grand_total' => 100,
            'proposed_at' => now()->subDays(2),
            'applied_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);
        DB::table('orders')->where('id', $orderId)->update(['applied_revision_id' => $revisionId]);
        DB::table('order_revision_items')->insert([
            'order_revision_id' => $revisionId,
            'order_item_id' => $orderItemId,
            'line_no' => 1,
            'product_id' => $productId,
            'sku_snapshot' => 'HISTORIC-SKU',
            'product_name_snapshot' => 'Historic product name',
            'quantity' => 1,
            'regular_unit_price_snapshot' => 100,
            'charged_unit_price' => 100,
            'discount_amount' => 0,
            'line_total' => 100,
            'currency' => 'EGP',
            'created_at' => now()->subDays(2),
        ]);

        return $orderItemId;
    }
}
