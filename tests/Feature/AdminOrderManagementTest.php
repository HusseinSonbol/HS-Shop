<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_routes_require_order_management_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_shipping_company_and_shipment_routes_require_shipping_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $orderId = $this->createOrder($staff);

        $this->actingAs($staff)->get(route('admin.shipping-companies.index'))->assertForbidden();
        $this->post(route('admin.orders.shipments.store', $orderId), [])->assertForbidden();
    }

    public function test_staff_can_search_orders_and_inspect_snapshot_and_independent_statuses(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $orderId = $this->createOrder($staff);
        DB::table('payments')->insert([
            'order_id' => $orderId, 'payment_number' => 'PAY-TEST-1', 'method' => 'cod', 'status' => 'pending',
            'amount' => '120.0000', 'currency' => 'EGP', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipments')->insert([
            'shipment_number' => 'SHP-TEST-1', 'order_id' => $orderId, 'shipping_company_id' => null, 'leg_type' => 'outbound',
            'status' => 'awaiting_pickup', 'tracking_number' => null, 'customer_shipping_charge' => '20.0000',
            'carrier_cost_actual' => null, 'expected_cod_collection' => '120.0000', 'actual_cod_collected' => null,
            'currency' => 'EGP', 'shipped_at' => null, 'delivered_at' => null,
            'recipient_snapshot' => json_encode(['recipient_name' => 'Test customer']), 'created_by' => $staff->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createInitialRevision($orderId);

        $this->actingAs($staff)
            ->get(route('admin.orders.index', ['q' => 'HS-TEST-ORDER']))
            ->assertOk()->assertSee('HS-TEST-ORDER')->assertSee('awaiting_pickup')->assertDontSee('pending')->assertDontSee('Payment status');

        $this->get(route('admin.orders.show', $orderId))
            ->assertOk()->assertSee('Test snapshot item')->assertSee('products/test-snapshot.jpg')->assertSee('Shirt / Large')->assertDontSee('pending')
            ->assertDontSee('Payment status')
            ->assertSee(__('admin.shipments.statuses.awaiting_pickup'))->assertSee(__('admin.orders.lifecycle_note'))
            ->assertSee(__('admin.orders.close_failure'));
    }

    public function test_assignment_and_note_are_append_only_events_with_actor_and_previous_values(): void
    {
        $operator = $this->createStaff(['dashboard.access', 'orders.manage'], 'operator');
        $assignee = $this->createStaff(['dashboard.access', 'orders.manage'], 'manager');
        $orderId = $this->createOrder($operator);

        $this->actingAs($operator)->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'follow_up_update', 'assigned_user_id' => $assignee->id,
            'note' => 'اتصلت بالعميل لتأكيد التفاصيل',
        ])->assertRedirect(route('admin.orders.show', $orderId));

        $this->assertDatabaseHas('order_activity_events', [
            'order_id' => $orderId, 'actor_user_id' => $operator->id, 'event_type' => 'assigned',
            'previous_status' => 'new', 'current_status' => 'new',
        ]);
        $assignment = DB::table('order_activity_events')->where('order_id', $orderId)->where('event_type', 'assigned')->first();
        $assignmentDetails = json_decode($assignment->details, true);
        $this->assertSame($assignee->id, $assignmentDetails['new_assignee']['id']);
        $this->assertNull($assignmentDetails['previous_assignee']);

        $note = DB::table('order_activity_events')->where('order_id', $orderId)->where('event_type', 'note_added')->first();
        $this->assertSame('اتصلت بالعميل لتأكيد التفاصيل', json_decode($note->details, true)['note']);
        $this->get(route('admin.orders.show', $orderId))->assertOk()->assertSee($assignee->name)->assertSee('اتصلت بالعميل لتأكيد التفاصيل');
        $this->assertSame('new', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    public function test_follow_up_is_saved_in_utc_from_cairo_local_time_and_rejects_past_values(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $orderId = $this->createOrder($staff);
        $localTime = now('Africa/Cairo')->addHour()->startOfMinute();

        $this->actingAs($staff)->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'follow_up_update', 'next_follow_up_at' => $localTime->format('Y-m-d\\TH:i'),
        ])->assertRedirect(route('admin.orders.show', $orderId));

        $event = DB::table('order_activity_events')->where('order_id', $orderId)->where('event_type', 'follow_up')->firstOrFail();
        $this->assertSame($localTime->utc()->format('Y-m-d H:i:s'), $event->next_follow_up_at);

        $this->from(route('admin.orders.show', $orderId))->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'follow_up_update', 'next_follow_up_at' => now('Africa/Cairo')->subHour()->format('Y-m-d\\TH:i'),
        ])->assertRedirect(route('admin.orders.show', $orderId))->assertSessionHasErrors('next_follow_up_at');
    }

    public function test_only_approved_order_status_transitions_are_allowed_and_success_requires_customer_receipt(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $orderId = $this->createOrder($staff);
        $this->actingAs($staff);

        $this->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'status_changed', 'new_status' => 'shipped',
        ])->assertSessionHasErrors('new_status');
        $this->assertSame('new', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertDatabaseCount('order_activity_events', 0);

        foreach (['confirmed', 'shipped'] as $nextStatus) {
            $this->post(route('admin.orders.events.store', $orderId), [
                'event_type' => 'status_changed', 'new_status' => $nextStatus,
            ])->assertRedirect(route('admin.orders.show', $orderId));
        }

        $this->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'status_changed', 'new_status' => 'received',
        ])->assertSessionHasErrors('customer_received');
        $this->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'status_changed', 'new_status' => 'received', 'customer_received' => '1',
        ])->assertRedirect(route('admin.orders.show', $orderId));

        $closedOrder = DB::table('orders')->where('id', $orderId)->first();
        $this->assertSame('received', $closedOrder->status);
        $this->assertSame('success', $closedOrder->close_outcome);
        $this->assertNotNull($closedOrder->closed_at);
        $this->assertDatabaseHas('order_activity_events', [
            'order_id' => $orderId, 'event_type' => 'status_changed', 'previous_status' => 'shipped',
            'current_status' => 'received', 'close_outcome' => 'success',
        ]);
        $this->get(route('admin.orders.show', $orderId))->assertOk()->assertSee(__('admin.orders.outcomes.success'));
        $this->post(route('admin.orders.events.store', $orderId), ['event_type' => 'reopened'])
            ->assertSessionHasErrors('event_type');
    }

    public function test_failed_closure_requires_reason_and_reopen_preserves_operational_status(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage']);
        $orderId = $this->createOrder($staff);
        $this->actingAs($staff)->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'closed', 'outcome' => 'failure',
        ])->assertSessionHasErrors('reason_code');

        $this->post(route('admin.orders.events.store', $orderId), [
            'event_type' => 'closed', 'outcome' => 'failure', 'reason_code' => 'customer_refused',
        ])->assertRedirect(route('admin.orders.show', $orderId));
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'new', 'close_outcome' => 'failure', 'closure_reason_code' => 'customer_refused']);
        $this->get(route('admin.orders.show', $orderId))
            ->assertOk()
            ->assertSee(__('admin.orders.closed_as_failure', ['reason' => __('admin.orders.failure_reasons.customer_refused')]))
            ->assertSee(__('admin.orders.reopen'));

        $this->post(route('admin.orders.events.store', $orderId), ['event_type' => 'reopened'])
            ->assertRedirect(route('admin.orders.show', $orderId));
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'new', 'close_outcome' => null, 'closure_reason_code' => null]);
        $this->assertDatabaseHas('order_activity_events', [
            'order_id' => $orderId, 'event_type' => 'closed', 'previous_status' => 'new', 'close_outcome' => 'failure', 'reason_code' => 'customer_refused',
        ]);
        $this->assertDatabaseHas('order_activity_events', [
            'order_id' => $orderId, 'event_type' => 'reopened', 'previous_status' => 'new', 'current_status' => 'new',
        ]);
    }

    public function test_manual_shipment_records_carrier_cost_collection_and_independent_status_history(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'orders.manage', 'shipments.manage']);
        $orderId = $this->createOrder($staff);
        $this->createInitialRevision($orderId);
        DB::table('payments')->insert([
            'order_id' => $orderId, 'payment_number' => 'PAY-SHIP-1', 'method' => 'cod', 'status' => 'pending',
            'amount' => '120.0000', 'currency' => 'EGP', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('admin.shipping-companies.store'), ['name' => 'Manual Carrier'])
            ->assertRedirect(route('admin.shipping-companies.index'));
        $this->get(route('admin.shipping-companies.index'))->assertOk()->assertSee('Manual Carrier')->assertSee(__('admin.shipping_companies.title'));
        $companyId = (int) DB::table('shipping_companies')->value('id');
        $key = (string) Str::uuid();
        $shipmentData = [
            'shipping_company_id' => $companyId, 'tracking_number' => 'TRK-1', 'customer_shipping_charge' => '20.0000',
            'carrier_cost_actual' => '35.0000', 'description' => 'Handed to carrier', 'idempotency_key' => $key,
        ];
        $this->post(route('admin.orders.shipments.store', $orderId), $shipmentData)->assertSessionHasErrors('shipment');
        $this->assertDatabaseCount('shipments', 0);
        foreach (['confirmed', 'shipped'] as $status) {
            $this->post(route('admin.orders.events.store', $orderId), ['event_type' => 'status_changed', 'new_status' => $status])
                ->assertRedirect(route('admin.orders.show', $orderId));
        }
        $this->post(route('admin.orders.shipments.store', $orderId), [
            ...$shipmentData,
        ])->assertRedirect(route('admin.orders.show', $orderId));
        $this->post(route('admin.orders.shipments.store', $orderId), [
            ...$shipmentData,
        ])->assertSessionHas('status', __('admin.shipments.already_created'));

        $shipment = DB::table('shipments')->where('order_id', $orderId)->firstOrFail();
        $this->assertSame('picked_up', $shipment->status);
        $this->assertSame('120', (string) $shipment->expected_cod_collection);
        $this->assertSame('shipped', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertDatabaseHas('shipment_items', ['shipment_id' => $shipment->id, 'quantity' => '1.000']);
        $this->put(route('admin.shipping-companies.update', $companyId), ['name' => 'Renamed Carrier'])->assertRedirect(route('admin.shipping-companies.index'));

        DB::table('orders')->where('id', $orderId)->update(['status' => 'confirmed']);
        $this->post(route('admin.orders.shipments.events.store', [$orderId, $shipment->id]), [
            'status' => 'picked_up', 'event_at' => now('Africa/Cairo')->format('Y-m-d\\TH:i'), 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('shipment');
        $this->assertDatabaseCount('shipment_events', 1);
        DB::table('orders')->where('id', $orderId)->update(['status' => 'shipped']);

        foreach (['in_transit', 'out_for_delivery'] as $status) {
            $this->post(route('admin.orders.shipments.events.store', [$orderId, $shipment->id]), [
                'status' => $status, 'event_at' => now('Africa/Cairo')->subMinute()->format('Y-m-d\\TH:i'), 'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect(route('admin.orders.show', $orderId));
        }
        $this->post(route('admin.orders.shipments.events.store', [$orderId, $shipment->id]), [
            'status' => 'delivered', 'event_at' => now('Africa/Cairo')->subMinute()->format('Y-m-d\\TH:i'),
            'actual_cod_collected' => '110.0000', 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('admin.orders.show', $orderId));

        $shipment = DB::table('shipments')->where('id', $shipment->id)->firstOrFail();
        $this->assertSame('delivered', $shipment->status);
        $this->assertSame('110', (string) $shipment->actual_cod_collected);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('shipped', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertSame('pending', DB::table('payments')->where('order_id', $orderId)->value('status'));
        $this->assertDatabaseCount('shipment_events', 4);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'status' => 'delivered', 'actor_user_id' => $staff->id]);
        $this->get(route('admin.orders.show', $orderId))->assertOk()->assertSee('Manual Carrier')->assertDontSee('Renamed Carrier')
            ->assertSee('110 EGP')->assertSee(__('admin.shipments.variance'))->assertSee('-10 EGP');
    }

    private function createOrder(User $creator): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'customer_number' => 'CUS-ORDER-'.Str::upper(Str::random(6)), 'display_name' => 'Test Customer',
            'phone' => '01012345678', 'email' => 'order-customer@example.test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('orders')->insertGetId([
            'order_number' => 'HS-TEST-ORDER', 'public_id' => (string) Str::uuid(), 'customer_id' => $customerId,
            'channel' => 'website', 'created_by_user_id' => $creator->id, 'status' => 'new', 'currency' => 'EGP',
            'subtotal' => '100.0000', 'discount_total' => '0.0000', 'customer_shipping_charge' => '20.0000',
            'tax_total' => null, 'grand_total' => '120.0000', 'applied_revision_id' => null, 'placed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createInitialRevision(int $orderId): void
    {
        $revisionId = DB::table('order_revisions')->insertGetId([
            'order_id' => $orderId, 'revision_no' => 1, 'revision_type' => 'initial', 'status' => 'applied',
            'currency' => 'EGP', 'subtotal' => '100.0000', 'discount_total' => '0.0000',
            'customer_shipping_charge' => '20.0000', 'tax_total' => null, 'grand_total' => '120.0000',
            'proposed_at' => now(), 'applied_at' => now(), 'created_at' => now(),
        ]);
        DB::table('orders')->where('id', $orderId)->update(['applied_revision_id' => $revisionId]);
        $itemId = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'line_key' => (string) Str::uuid(), 'created_at' => now()]);
        DB::table('order_revision_items')->insert([
            'order_revision_id' => $revisionId, 'order_item_id' => $itemId, 'line_no' => 1, 'sku_snapshot' => 'SKU-TEST',
            'product_name_snapshot' => 'Test snapshot item', 'product_image_path_snapshot' => 'products/test-snapshot.jpg', 'variant_snapshot' => json_encode(['name' => 'Shirt / Large']),
            'quantity' => '1.000', 'regular_unit_price_snapshot' => '100.0000', 'charged_unit_price' => '100.0000',
            'discount_amount' => '0.0000', 'line_total' => '100.0000', 'currency' => 'EGP', 'created_at' => now(),
        ]);
        DB::table('order_revision_addresses')->insert([
            'order_revision_id' => $revisionId, 'address_type' => 'shipping', 'recipient_name' => 'Test Recipient', 'phone' => '01012345678',
            'country_code' => 'EG', 'governorate' => 'Cairo', 'city' => 'Cairo', 'district' => null, 'street' => 'Test Street',
            'building' => null, 'floor' => null, 'apartment' => null, 'landmark' => null, 'postal_code' => null, 'created_at' => now(),
        ]);
    }

    /** @param array<int, string> $permissions */
    private function createStaff(array $permissions, string $roleCode = 'manager'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId([
            'code' => $roleCode, 'name' => ucfirst($roleCode), 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insert([
                'role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $permission)->value('id'),
            ]);
        }

        return $user;
    }
}
