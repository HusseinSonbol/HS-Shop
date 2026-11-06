<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReturnCaseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_routes_require_permission(): void
    {
        $staff = $this->staff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.returns.index'))->assertForbidden();
    }

    public function test_staff_opens_damaged_return_on_original_order_and_preserves_sale_closure(): void
    {
        $staff = $this->staff(['dashboard.access', 'returns.manage', 'orders.manage']);
        [$orderId, $lineId] = $this->deliveredOrder($staff);
        $originalClosure = DB::table('orders')->where('id', $orderId)->value('closed_at');

        $this->actingAs($staff)->post(route('admin.returns.store', $orderId), [
            'reason_code' => 'manufacturing_defect',
            'reason' => 'Seam is torn and has not been used.',
            'unused_claimed' => '1',
            'items' => [['order_item_id' => $lineId, 'quantity' => '1.000']],
        ])->assertRedirect();

        $case = DB::table('returns')->firstOrFail();
        $order = DB::table('orders')->where('id', $orderId)->firstOrFail();
        $this->assertSame('damaged_return', $order->status);
        $this->assertSame('success', $order->close_outcome);
        $this->assertSame($originalClosure, $order->closed_at);
        $this->assertSame('damaged_return', $case->case_type);
        $this->assertSame('requested', $case->status);
        $this->assertDatabaseHas('return_items', ['return_id' => $case->id, 'order_item_id' => $lineId, 'quantity' => '1.000']);
        $this->assertDatabaseHas('order_activity_events', ['order_id' => $orderId, 'event_type' => 'damaged_return_opened']);
        $this->get(route('admin.returns.index'))->assertOk()->assertSee($case->return_number);
        $this->get(route('admin.returns.show', $case->id))->assertOk()->assertSee($case->return_number)->assertSee(__('admin.returns.statuses.requested'));
    }

    public function test_return_quantity_cannot_exceed_remaining_order_line_quantity(): void
    {
        $staff = $this->staff(['dashboard.access', 'returns.manage']);
        [$orderId, $lineId] = $this->deliveredOrder($staff);

        $this->actingAs($staff)->post(route('admin.returns.store', $orderId), [
            'reason_code' => 'damaged', 'reason' => 'Product is damaged and unused.', 'unused_claimed' => '1',
            'items' => [['order_item_id' => $lineId, 'quantity' => '3.000']],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('returns', 0);
        $this->assertSame('received', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    public function test_product_cost_is_separate_from_operating_expenses_and_idempotent(): void
    {
        $staff = $this->staff(['dashboard.access', 'expenses.manage']);
        [$orderId, $lineId] = $this->deliveredOrder($staff);
        $key = (string) Str::uuid();
        $payload = [
            'cost_type' => 'product', 'order_item_id' => $lineId, 'basis' => 'actual',
            'amount' => '75.0000', 'description' => 'Factory cost for the ordered line', 'idempotency_key' => $key,
        ];

        $this->actingAs($staff)->post(route('admin.orders.costs.store', $orderId), $payload)->assertRedirect();
        $this->post(route('admin.orders.costs.store', $orderId), $payload)->assertRedirect();

        $this->assertDatabaseCount('order_cost_entries', 1);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('order_cost_entries', ['order_id' => $orderId, 'order_item_id' => $lineId, 'cost_type' => 'product', 'amount' => '75.0000']);
    }

    /** @return array{int, int} */
    private function deliveredOrder(User $creator): array
    {
        $customerId = DB::table('customers')->insertGetId([
            'customer_number' => 'CUS-RET-'.Str::upper(Str::random(6)), 'display_name' => 'Return Customer',
            'phone' => '01012345678', 'email' => 'return-'.Str::lower(Str::random(6)).'@example.test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $receivedAt = now()->subDay();
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'HS-RET-'.Str::upper(Str::random(8)), 'public_id' => (string) Str::uuid(), 'customer_id' => $customerId,
            'channel' => 'website', 'created_by_user_id' => $creator->id, 'status' => 'received', 'closed_at' => $receivedAt,
            'close_outcome' => 'success', 'currency' => 'EGP', 'subtotal' => '200.0000', 'discount_total' => '0.0000',
            'customer_shipping_charge' => '30.0000', 'tax_total' => null, 'grand_total' => '230.0000',
            'applied_revision_id' => null, 'placed_at' => $receivedAt->copy()->subDays(2), 'created_at' => $receivedAt, 'updated_at' => $receivedAt,
        ]);
        $revisionId = DB::table('order_revisions')->insertGetId([
            'order_id' => $orderId, 'revision_no' => 1, 'revision_type' => 'initial', 'status' => 'applied', 'currency' => 'EGP',
            'subtotal' => '200.0000', 'discount_total' => '0.0000', 'customer_shipping_charge' => '30.0000', 'tax_total' => null,
            'grand_total' => '230.0000', 'proposed_at' => $receivedAt, 'applied_at' => $receivedAt, 'created_at' => $receivedAt,
        ]);
        $lineId = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'line_key' => (string) Str::uuid(), 'created_at' => $receivedAt]);
        DB::table('order_revision_items')->insert([
            'order_revision_id' => $revisionId, 'order_item_id' => $lineId, 'line_no' => 1, 'sku_snapshot' => 'RET-SKU-1',
            'product_name_snapshot' => 'Test product', 'variant_snapshot' => json_encode(['variant_name' => 'Blue / Large']),
            'quantity' => '2.000', 'regular_unit_price_snapshot' => '100.0000', 'charged_unit_price' => '100.0000',
            'discount_amount' => '0.0000', 'line_total' => '200.0000', 'currency' => 'EGP', 'created_at' => $receivedAt,
        ]);
        DB::table('orders')->where('id', $orderId)->update(['applied_revision_id' => $revisionId]);

        return [$orderId, $lineId];
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId(['code' => 'manager', 'name' => 'Manager', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $code) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $code)->value('id')]);
        }

        return $user;
    }
}
