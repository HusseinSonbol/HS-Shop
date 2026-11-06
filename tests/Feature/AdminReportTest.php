<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_cairo_saturday_week_and_order_revision_and_separates_cost_bases(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'reports.view']);
        $customer = Customer::query()->create(['customer_number' => 'CUS-REPORT-1', 'display_name' => 'Report customer', 'phone' => '01000000100']);
        $placedAt = CarbonImmutable::parse('2026-11-04 10:30:00', 'Africa/Cairo')->utc();
        $orderId = $this->createOrder($customer->id, $placedAt, 'success');
        $revisionId = DB::table('order_revisions')->insertGetId([
            'order_id' => $orderId, 'revision_no' => 1, 'revision_type' => 'initial', 'status' => 'applied',
            'currency' => 'EGP', 'subtotal' => '100.0000', 'discount_total' => '10.0000',
            'customer_shipping_charge' => '15.0000', 'tax_total' => '0.0000', 'grand_total' => '105.0000',
            'proposed_by' => null, 'proposed_at' => $placedAt, 'expires_at' => null, 'decided_at' => null,
            'applied_at' => $placedAt, 'idempotency_key' => null, 'created_at' => $placedAt,
        ]);
        DB::table('orders')->where('id', $orderId)->update(['applied_revision_id' => $revisionId]);
        DB::table('order_activity_events')->insert([
            'order_id' => $orderId, 'actor_user_id' => $staff->id, 'event_type' => 'status_changed',
            'previous_status' => 'shipped', 'current_status' => 'received', 'close_outcome' => 'success', 'reason_code' => null,
            'details' => '{}', 'next_follow_up_at' => null, 'occurred_at' => $placedAt, 'idempotency_key' => 'report-received-1', 'created_at' => $placedAt,
        ]);
        $this->addCost($orderId, $staff->id, 'estimate', '12.0000', $placedAt);
        $this->addCost($orderId, $staff->id, 'actual', '8.0000', $placedAt);
        DB::table('refunds')->insert([
            'refund_number' => 'RFD-REPORT-1', 'return_id' => null, 'payment_id' => null, 'order_id' => $orderId,
            'status' => 'completed', 'amount' => '5.0000', 'currency' => 'EGP', 'reason' => 'Report test',
            'refunded_at' => $placedAt->addDay(), 'created_by' => $staff->id, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);
        DB::table('returns')->insert([
            'return_number' => 'RET-REPORT-1', 'order_id' => $orderId, 'customer_id' => $customer->id,
            'case_type' => 'damaged_return', 'status' => 'requested', 'reason_code' => 'damaged', 'reason' => 'Test damage',
            'requested_at' => $placedAt, 'received_at' => null, 'resolution' => null, 'notes' => null,
            'created_by' => $staff->id, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);
        DB::table('expenses')->insert([
            'expense_number' => 'EXP-REPORT-1', 'expense_type' => 'general', 'cost_category' => 'advertising',
            'description' => 'General campaign', 'amount' => '5.5000', 'currency' => 'EGP', 'expense_date' => '2026-11-04',
            'order_id' => null, 'status' => 'posted', 'created_by' => $staff->id, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);
        DB::table('shipments')->insert([
            'shipment_number' => 'SHP-REPORT-1', 'order_id' => $orderId, 'shipping_company_id' => null,
            'leg_type' => 'outbound', 'status' => 'delivered', 'tracking_number' => null,
            'customer_shipping_charge' => '15.0000', 'carrier_cost_actual' => '8.0000',
            'expected_cod_collection' => '105.0000', 'actual_cod_collected' => '105.0000', 'currency' => 'EGP',
            'shipped_at' => $placedAt, 'delivered_at' => $placedAt, 'recipient_snapshot' => '{}',
            'created_by' => $staff->id, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);

        $this->actingAs($staff)->get(route('admin.reports.index', ['period' => 'week', 'date' => '2026-11-04']))
            ->assertOk()->assertSee('2026-10-31')->assertSee('2026-11-06')
            ->assertSee('105.0000')->assertSee('8.0000')->assertSee('12.0000')->assertSee('92.0000')->assertSee('5.5000')
            ->assertSee(__('admin.reports.damaged_returns'))->assertSee(__('admin.reports.delivered_in_period'));
    }

    public function test_dashboard_metrics_and_report_are_permission_scoped(): void
    {
        $staff = $this->createStaff(['dashboard.access']);
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->assertDontSee(__('admin.reports.navigation'))
            ->assertDontSee(__('admin.dashboard.stats.orders_today'));
        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_report_bounds_follow_cairo_calendar_month_and_year(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'reports.view']);
        $this->actingAs($staff)
            ->get(route('admin.reports.index', ['period' => 'month', 'date' => '2026-02-19']))
            ->assertOk()->assertSee('2026-02-01')->assertSee('2026-02-28');

        $this->get(route('admin.reports.index', ['period' => 'year', 'date' => '2026-02-19']))
            ->assertOk()->assertSee('2026-01-01')->assertSee('2026-12-31');
    }

    private function createOrder(int $customerId, CarbonImmutable $placedAt, string $outcome): int
    {
        return (int) DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REPORT-1', 'public_id' => 'b80c83a5-35e7-4808-9a1c-5ce28e47321a',
            'customer_id' => $customerId, 'channel' => 'website', 'created_by_user_id' => null, 'source_reference' => null,
            'status' => 'received', 'closed_at' => $placedAt, 'close_outcome' => $outcome, 'closure_reason_code' => null,
            'currency' => 'EGP', 'subtotal' => '100.0000', 'discount_total' => '10.0000',
            'customer_shipping_charge' => '15.0000', 'tax_total' => '0.0000', 'grand_total' => '105.0000',
            'applied_revision_id' => null, 'customer_note' => null, 'placed_at' => $placedAt,
            'cancelled_at' => null, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);
    }

    private function addCost(int $orderId, int $actorId, string $basis, string $amount, CarbonImmutable $at): void
    {
        DB::table('order_cost_entries')->insert([
            'order_id' => $orderId, 'order_item_id' => null, 'shipment_id' => null, 'cost_type' => 'packaging',
            'amount' => $amount, 'currency' => 'EGP', 'basis' => $basis, 'expense_id' => null,
            'recorded_at' => $at, 'recorded_by' => $actorId, 'reverses_entry_id' => null,
            'notes' => null, 'created_at' => $at,
        ]);
    }

    /** @param list<string> $permissions */
    private function createStaff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleCode = 'report-test-'.substr(md5(implode('-', $permissions).$user->id), 0, 12);
        DB::table('roles')->insert(['code' => $roleCode, 'name' => 'Report test staff', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $roleCode)->value('id'), 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => DB::table('roles')->where('code', $roleCode)->value('id'),
                'permission_id' => DB::table('permissions')->where('code', $permission)->value('id'),
            ]);
        }

        return $user;
    }
}
