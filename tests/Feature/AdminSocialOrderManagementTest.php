<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSocialOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_order_form_and_actions_require_order_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.orders.create-social'))->assertForbidden();
        $this->post(route('admin.orders.store-social'), $this->orderData())->assertForbidden();
    }

    public function test_staff_can_create_instapay_social_order_and_proof_is_private_until_reviewed(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $customerId = $this->createCustomer();
        $variantId = $this->createAvailableVariant();
        $this->createShippingRates();

        $this->actingAs($staff)->get(route('admin.orders.create-social'))
            ->assertOk()->assertSee(__('admin.social_orders.create_title'))->assertSee('instapay');

        $data = $this->orderData($customerId, $variantId, [
            'channel' => 'instagram',
            'payment_method' => 'instapay',
            'payment_proof' => UploadedFile::fake()->image('transfer.jpg'),
            'items' => [['variant_id' => $variantId, 'quantity' => 2]],
        ]);
        $response = $this->post(route('admin.orders.store-social'), $data)->assertRedirect();
        $orderId = (int) DB::table('orders')->value('id');
        $order = DB::table('orders')->where('id', $orderId)->first();

        $this->assertSame('instagram', $order->channel);
        $this->assertSame($staff->id, $order->created_by_user_id);
        $this->assertSame('new', $order->status);
        $this->assertEquals(220, $order->grand_total);
        $this->assertDatabaseHas('order_revisions', ['order_id' => $orderId, 'revision_no' => 1, 'status' => 'applied']);
        $this->assertDatabaseHas('order_revision_items', [
            'order_revision_id' => $order->applied_revision_id,
            'product_name_snapshot' => 'Test bedding set',
            'variant_id' => $variantId,
            'quantity' => '2.000',
            'charged_unit_price' => '90.0000',
        ]);
        $payment = DB::table('payments')->where('order_id', $orderId)->first();
        $this->assertSame('instapay', $payment->method);
        $this->assertSame('pending', $payment->status, 'Uploading proof must not mark payment as paid.');
        $proof = DB::table('payment_proofs')->first();
        $this->assertSame('pending', $proof->review_status);
        Storage::disk('local')->assertExists($proof->storage_path);
        Storage::disk('public')->assertMissing($proof->storage_path);

        $proofUrl = route('admin.orders.payment-proofs.show', [$orderId, $proof->id]);
        $this->get($proofUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('admin.orders.show', $orderId))->assertOk()
            ->assertSee(__('admin.social_orders.instapay_review'))
            ->assertSee(__('admin.social_orders.statuses.pending'));

        $this->post(route('admin.orders.payment-proofs.review', [$orderId, $proof->id]), [
            'decision' => 'accepted',
        ])->assertRedirect(route('admin.orders.show', $orderId));

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id, 'review_status' => 'accepted', 'reviewed_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $payment->id, 'transaction_type' => 'verification', 'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('order_activity_events', [
            'order_id' => $orderId, 'event_type' => 'payment_proof_reviewed', 'actor_user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'order.instapay_proof_reviewed', 'subject_id' => $orderId]);
        $this->get(route('admin.orders.show', $orderId))->assertSee(__('admin.social_orders.payment_received'));

        $this->from(route('admin.orders.show', $orderId))
            ->post(route('admin.orders.payment-proofs.review', [$orderId, $proof->id]), ['decision' => 'accepted'])
            ->assertRedirect(route('admin.orders.show', $orderId))->assertSessionHasErrors('decision');
        $this->assertSame(2, DB::table('payment_transactions')->where('payment_id', $payment->id)->count());
    }

    public function test_instapay_rejection_requires_a_reason_and_leaves_payment_unpaid(): void
    {
        Storage::fake('local');
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $customerId = $this->createCustomer();
        $variantId = $this->createAvailableVariant();
        $this->createShippingRates();
        $this->actingAs($staff)->post(route('admin.orders.store-social'), $this->orderData($customerId, $variantId, [
            'payment_method' => 'instapay', 'payment_proof' => UploadedFile::fake()->image('proof.png'),
        ]))->assertRedirect();

        $orderId = (int) DB::table('orders')->value('id');
        $proofId = (int) DB::table('payment_proofs')->value('id');
        $this->from(route('admin.orders.show', $orderId))
            ->post(route('admin.orders.payment-proofs.review', [$orderId, $proofId]), ['decision' => 'rejected'])
            ->assertRedirect(route('admin.orders.show', $orderId))->assertSessionHasErrors('rejection_reason');
        $this->assertDatabaseHas('payment_proofs', ['id' => $proofId, 'review_status' => 'pending']);

        $this->post(route('admin.orders.payment-proofs.review', [$orderId, $proofId]), [
            'decision' => 'rejected', 'rejection_reason' => 'بيانات التحويل غير واضحة',
        ])->assertRedirect(route('admin.orders.show', $orderId));
        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proofId, 'review_status' => 'rejected', 'rejection_reason' => 'بيانات التحويل غير واضحة',
        ]);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'instapay', 'status' => 'pending']);
        $this->assertDatabaseHas('payment_transactions', ['transaction_type' => 'verification', 'status' => 'failed']);

        $this->post(route('admin.orders.payment-proofs.store', $orderId), [
            'payment_proof' => UploadedFile::fake()->image('replacement.png'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('admin.orders.show', $orderId));
        $this->assertSame(2, DB::table('payment_proofs')->count());
        $this->assertSame('pending', DB::table('payment_proofs')->orderByDesc('id')->value('review_status'));
    }

    public function test_instapay_proof_is_required_and_web_orders_are_not_accepted_as_social_orders(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $customerId = $this->createCustomer();
        $variantId = $this->createAvailableVariant();
        $this->createShippingRates();
        $this->actingAs($staff)->from(route('admin.orders.create-social'))
            ->post(route('admin.orders.store-social'), $this->orderData($customerId, $variantId, [
                'payment_method' => 'instapay',
            ]))->assertRedirect(route('admin.orders.create-social'))->assertSessionHasErrors('payment_proof');

        $this->from(route('admin.orders.create-social'))
            ->post(route('admin.orders.store-social'), $this->orderData($customerId, $variantId, [
                'channel' => 'website',
                'payment_method' => 'instapay',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]))->assertRedirect(route('admin.orders.create-social'))->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_repeated_social_order_submission_with_same_key_is_idempotent(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $customerId = $this->createCustomer();
        $variantId = $this->createAvailableVariant();
        $this->createShippingRates();
        $data = $this->orderData($customerId, $variantId);

        $this->actingAs($staff)->post(route('admin.orders.store-social'), $data)->assertRedirect();
        $this->post(route('admin.orders.store-social'), $data)->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_revisions', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    private function createCustomer(): int
    {
        return (int) DB::table('customers')->insertGetId([
            'customer_number' => 'CUS-SOCIAL-'.Str::upper(Str::random(6)),
            'display_name' => 'Social Customer',
            'phone' => '01012345678',
            'email' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAvailableVariant(string $availability = 'available'): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'brand_id' => null,
            'product_type' => 'variable',
            'sku' => 'SOCIAL-PRODUCT-'.Str::upper(Str::random(5)),
            'slug' => 'social-product-'.Str::lower(Str::random(5)),
            'status' => 'published',
            'store_availability_status' => $availability,
            'availability_checked_at' => now(),
            'availability_checked_by' => null,
            'requires_shipping' => true,
            'tax_class' => null,
            'weight_g' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_translations')->insert([
            'product_id' => $productId, 'locale' => 'en', 'name' => 'Test bedding set',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SOCIAL-VARIANT-'.Str::upper(Str::random(5)),
            'barcode' => null,
            'variant_name' => 'Queen',
            'is_default' => true,
            'is_active' => true,
            'current_regular_price' => '100.0000',
            'current_sale_price' => '90.0000',
            'currency' => 'EGP',
            'current_price_version_id' => null,
            'weight_g' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createShippingRates(): void
    {
        DB::table('settings')->insert([
            'group' => 'shipping', 'key' => 'governorate_rates',
            'value' => json_encode([[
                'code' => (string) Str::uuid(), 'governorate_ar' => 'القاهرة', 'governorate_en' => 'Cairo', 'charge' => '40.0000',
            ]], JSON_THROW_ON_ERROR),
            'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function orderData(?int $customerId = null, ?int $variantId = null, array $overrides = []): array
    {
        $customerId ??= $this->createCustomer();
        $variantId ??= $this->createAvailableVariant();

        return array_replace_recursive([
            'customer_id' => $customerId,
            'channel' => 'facebook',
            'source_reference' => 'Conversation #58',
            'items' => [['variant_id' => $variantId, 'quantity' => 2]],
            'shipping_rate_code' => DB::table('settings')->where('key', 'governorate_rates')->value('id')
                ? json_decode(DB::table('settings')->where('key', 'governorate_rates')->value('value'), true)[0]['code']
                : (string) Str::uuid(),
            'shipping_address' => [
                'recipient_name' => 'Social Customer', 'phone' => '01012345678', 'city' => 'Cairo',
                'district' => 'Nasr City', 'street' => 'Test Street', 'building' => '10', 'floor' => '2',
                'apartment' => '4', 'landmark' => 'Near the square', 'postal_code' => '11765',
            ],
            'payment_method' => 'cod',
            'customer_note' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    /** @param list<string> $permissions */
    private function createStaff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId([
            'code' => 'manager', 'name' => 'Manager', 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => DB::table('permissions')->where('code', $permission)->value('id'),
            ]);
        }

        return $user;
    }
}
