<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_and_feedback_queues_have_separate_permissions(): void
    {
        $reviewStaff = $this->createStaff(['dashboard.access', 'reviews.moderate']);
        $this->actingAs($reviewStaff)->get(route('admin.reviews.index'))->assertOk()->assertSee(__('admin.reviews.title'));
        $this->get(route('admin.feedback.index'))->assertForbidden();

        $feedbackStaff = $this->createStaff(['dashboard.access', 'feedback.moderate']);
        $this->actingAs($feedbackStaff)->get(route('admin.reviews.index'))->assertForbidden();
        $this->get(route('admin.feedback.index'))->assertOk()->assertSee(__('admin.feedback.title'));
    }

    public function test_new_review_stays_hidden_until_separately_approved_and_published(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'reviews.moderate']);
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        $reviewId = DB::table('product_reviews')->insertGetId([
            'product_id' => $product->id, 'customer_id' => $customer->id, 'rating' => 4,
            'comment' => 'Good quality', 'moderation_status' => 'pending', 'visibility_status' => 'hidden', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $review = ProductReview::query()->findOrFail($reviewId);
        $this->actingAs($manager)->get(route('admin.reviews.index'))
            ->assertOk()->assertSee('Good quality')->assertSee('name="moderation_status" value="approved"', false)
            ->assertDontSee('name="visibility_status"', false)->assertDontSee('out of 5');

        $this->patch(route('admin.reviews.moderation', $review), ['moderation_status' => 'approved'])
            ->assertRedirect(route('admin.reviews.index', ['scope' => 'all']));
        $review->refresh();
        $this->assertSame('approved', $review->moderation_status);
        $this->assertSame('hidden', $review->visibility_status);
        $this->assertSame($manager->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
        $this->get(route('admin.reviews.index', ['scope' => 'all']))
            ->assertOk()->assertSee('name="visibility_status"', false);

        $this->patch(route('admin.reviews.visibility', $review), ['visibility_status' => 'visible'])->assertRedirect();
        $this->assertSame('visible', $review->fresh()->visibility_status);
        $this->patch(route('admin.reviews.visibility', $review), ['visibility_status' => 'hidden'])->assertRedirect();
        $review->refresh();
        $this->assertSame('approved', $review->moderation_status);
        $this->assertSame('hidden', $review->visibility_status);
        $this->assertDatabaseHas('product_reviews', ['id' => $review->id, 'comment' => 'Good quality']);
        $this->assertDatabaseCount('activity_logs', 3);
    }

    public function test_rejected_review_cannot_be_made_visible_and_is_not_deleted(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'reviews.moderate']);
        $customer = $this->createCustomer();
        $product = $this->createProduct();
        $reviewId = DB::table('product_reviews')->insertGetId([
            'product_id' => $product->id, 'customer_id' => $customer->id, 'rating' => 2,
            'comment' => 'Not relevant', 'moderation_status' => 'pending', 'visibility_status' => 'hidden', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $review = ProductReview::query()->findOrFail($reviewId);
        $this->actingAs($manager)->patch(route('admin.reviews.moderation', $review), [
            'moderation_status' => 'rejected', 'rejection_reason' => 'Does not describe a product experience.',
        ])->assertRedirect();
        $this->patch(route('admin.reviews.visibility', $review), ['visibility_status' => 'visible'])->assertSessionHasErrors('visibility_status');
        $review->refresh();
        $this->assertSame('rejected', $review->moderation_status);
        $this->assertSame('hidden', $review->visibility_status);
        $this->assertNotNull(ProductReview::query()->find($review->id));
        $this->assertDatabaseHas('activity_logs', ['event' => 'product_review.moderated']);
    }

    public function test_customer_feedback_visibility_and_homepage_selection_are_independent_and_moderated(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'feedback.moderate']);
        $feedbackId = DB::table('customer_feedback')->insertGetId([
            'feedback_number' => 'FB-TEST-001', 'sender_name_snapshot' => 'Test sender',
            'sender_email_snapshot' => 'sender@example.test', 'feedback_text' => 'Helpful support.',
            'moderation_status' => 'pending', 'visibility_status' => 'hidden', 'show_on_homepage' => false, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $feedback = CustomerFeedback::query()->findOrFail($feedbackId);
        $this->actingAs($manager)->get(route('admin.feedback.index'))->assertOk()->assertSee('Helpful support.')->assertSee('Test sender');

        $this->patch(route('admin.feedback.visibility', $feedback), ['visibility_status' => 'visible', 'show_on_homepage' => '1'])
            ->assertSessionHasErrors(['show_on_homepage']);
        $this->patch(route('admin.feedback.moderation', $feedback), ['moderation_status' => 'approved', 'staff_note' => 'Checked'])
            ->assertRedirect();
        $feedback->refresh();
        $this->assertSame('hidden', $feedback->visibility_status);
        $this->assertFalse($feedback->show_on_homepage);

        $this->patch(route('admin.feedback.visibility', $feedback), ['visibility_status' => 'visible', 'show_on_homepage' => '1'])->assertRedirect();
        $this->assertSame('visible', $feedback->fresh()->visibility_status);
        $this->assertTrue($feedback->fresh()->show_on_homepage);
        $this->patch(route('admin.feedback.visibility', $feedback), ['visibility_status' => 'hidden', 'show_on_homepage' => '0'])->assertRedirect();
        $feedback->refresh();
        $this->assertSame('approved', $feedback->moderation_status);
        $this->assertSame('hidden', $feedback->visibility_status);
        $this->assertFalse($feedback->show_on_homepage);
        $this->assertNotNull(CustomerFeedback::query()->find($feedback->id));
        $this->assertDatabaseHas('activity_logs', ['event' => 'customer_feedback.visibility_changed', 'actor_user_id' => $manager->id]);
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create(['customer_number' => 'CUS-TEST-001', 'display_name' => 'Test customer', 'phone' => '01012345678']);
    }

    private function createProduct(): Product
    {
        $product = Product::query()->create([
            'product_type' => 'simple', 'sku' => 'REVIEW-TEST', 'slug' => 'review-test', 'status' => 'published',
            'store_availability_status' => 'available', 'requires_shipping' => true, 'published_at' => now(),
        ]);
        $product->translations()->createMany([
            ['locale' => 'ar', 'name' => 'منتج تجريبي'], ['locale' => 'en', 'name' => 'Sample product'],
        ]);

        return $product;
    }

    /** @param list<string> $permissions */
    private function createStaff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleCode = 'test-manager-'.implode('-', $permissions);
        DB::table('roles')->insertOrIgnore(['code' => $roleCode, 'name' => 'Test manager', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $permission)->value('id')]);
        }

        return $user;
    }
}
