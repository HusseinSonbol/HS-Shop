<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_routes_require_customer_management_permission(): void
    {
        $user = $this->createStaff(['dashboard.access']);

        $this->actingAs($user)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_owner_can_search_create_edit_and_view_customer_details(): void
    {
        $owner = $this->createStaff(['dashboard.access', 'customers.manage']);
        $this->actingAs($owner);

        $this->get(route('admin.customers.index'))->assertOk()->assertSee(__('admin.customers.title'));
        $this->get(route('admin.customers.create'))->assertOk()->assertSee(__('admin.customers.create_title'));

        $this->post(route('admin.customers.store'), [
            'display_name' => 'عميل تجريبي',
            'phone' => '٠١٠ ١٢٣٤ ٥٦٧٨',
            'email' => 'customer@example.test',
            'notes' => 'ملاحظة داخلية',
        ])->assertRedirect();

        $customer = Customer::query()->where('email', 'customer@example.test')->firstOrFail();
        $this->assertSame('010 1234 5678', $customer->phone);
        $this->assertStringStartsWith('CUS-', $customer->customer_number);
        $this->assertDatabaseHas('activity_logs', ['event' => 'customer.created', 'subject_id' => $customer->id]);

        $this->get(route('admin.customers.index', ['q' => 'customer@example.test']))
            ->assertOk()
            ->assertSee($customer->customer_number);
        $this->get(route('admin.customers.edit', $customer))->assertOk();
        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('عميل تجريبي')
            ->assertSee('ملاحظة داخلية');

        $this->put(route('admin.customers.update', $customer), [
            'display_name' => 'عميل تم تحديثه',
            'phone' => '+201012345678',
            'email' => 'CUSTOMER@example.test',
            'notes' => '',
        ])->assertRedirect(route('admin.customers.show', $customer));

        $this->assertSame('customer@example.test', $customer->fresh()->email);
        $this->assertSame('عميل تم تحديثه', $customer->fresh()->display_name);
        $this->assertDatabaseHas('activity_logs', ['event' => 'customer.updated', 'subject_id' => $customer->id]);
    }

    public function test_customer_addresses_are_scoped_validated_and_soft_deleted(): void
    {
        $owner = $this->createStaff(['dashboard.access', 'customers.manage']);
        $this->actingAs($owner);
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer('OTHER-ADDRESS');

        $this->get(route('admin.customers.addresses.create', $customer))->assertOk();
        $this->post(route('admin.customers.addresses.store', $customer), $this->addressData([
            'phone' => '٠١١ ٢٢٢٢ ٣٣٣٣',
            'country_code' => 'eg',
            'is_default' => '1',
        ]))->assertRedirect(route('admin.customers.show', $customer));

        $address = $customer->addresses()->firstOrFail();
        $this->assertSame('011 2222 3333', $address->phone);
        $this->assertSame('EG', $address->country_code);
        $this->assertTrue($address->is_default);
        $this->get(route('admin.customers.addresses.edit', [$customer, $address]))->assertOk();

        $this->post(route('admin.customers.addresses.store', $customer), $this->addressData([
            'label' => 'العمل',
            'is_default' => '1',
        ]))->assertRedirect(route('admin.customers.show', $customer));
        $secondAddress = $customer->addresses()->where('label', 'العمل')->firstOrFail();
        $this->assertTrue($secondAddress->is_default);
        $this->assertFalse($address->fresh()->is_default);

        $this->put(route('admin.customers.addresses.update', [$customer, $address]), $this->addressData([
            'label' => 'العمل',
            'is_default' => '1',
        ]))->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame('العمل', $address->fresh()->label);
        $this->assertFalse($secondAddress->fresh()->is_default);

        $this->get(route('admin.customers.addresses.edit', [$otherCustomer, $address]))->assertNotFound();
        $this->delete(route('admin.customers.addresses.archive', [$customer, $address]))
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
    }

    public function test_archiving_customer_preserves_order_and_can_be_restored(): void
    {
        $owner = $this->createStaff(['dashboard.access', 'customers.manage']);
        $this->actingAs($owner);
        $customer = $this->createCustomer();
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'HS-TEST-10001',
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'customer_id' => $customer->id,
            'channel' => 'facebook',
            'status' => 'new',
            'currency' => 'EGP',
            'subtotal' => '120.0000',
            'discount_total' => '0.0000',
            'customer_shipping_charge' => '50.0000',
            'grand_total' => '170.0000',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete(route('admin.customers.archive', $customer))
            ->assertRedirect(route('admin.customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'customer_id' => $customer->id]);
        $this->get(route('admin.customers.index', ['scope' => 'archived']))
            ->assertOk()
            ->assertSee($customer->customer_number);

        $this->get(route('admin.customers.show', $customer->id))
            ->assertOk()
            ->assertSee('HS-TEST-10001')
            ->assertSee(__('admin.customers.archived_notice'));
        $this->patch(route('admin.customers.restore', $customer->id))
            ->assertRedirect(route('admin.customers.show', $customer->id));
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_customer_validation_has_localized_phone_and_email_errors(): void
    {
        $owner = $this->createStaff(['dashboard.access', 'customers.manage']);
        $this->actingAs($owner)->withSession(['locale' => 'en']);

        $this->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'display_name' => 'Invalid customer',
                'phone' => 'not-a-phone',
                'email' => 'not-an-email',
            ])
            ->assertRedirect(route('admin.customers.create'))
            ->assertSessionHasErrors(['phone', 'email']);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function addressData(array $overrides = []): array
    {
        return array_replace([
            'label' => 'المنزل',
            'recipient_name' => 'مستلم تجريبي',
            'phone' => '01012345678',
            'country_code' => 'EG',
            'governorate' => 'القاهرة',
            'city' => 'مدينة نصر',
            'district' => 'الحي السابع',
            'street' => 'شارع تجريبي 1',
            'building' => '10',
            'floor' => '2',
            'apartment' => '5',
            'landmark' => '',
            'postal_code' => '',
            'is_default' => '0',
        ], $overrides);
    }

    private function createCustomer(string $numberSuffix = 'CUSTOMER'): Customer
    {
        return Customer::query()->create([
            'customer_number' => 'CUS-'.$numberSuffix.'-'.fake()->unique()->numerify('####'),
            'display_name' => 'Test Customer',
            'phone' => '01012345678',
            'email' => null,
        ]);
    }

    /** @param array<int, string> $permissionCodes */
    private function createStaff(array $permissionCodes): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId([
            'code' => 'manager',
            'name' => 'Manager',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'assigned_by' => null,
            'created_at' => now(),
        ]);

        foreach ($permissionCodes as $permissionCode) {
            DB::table('permissions')->insertOrIgnore([
                'code' => $permissionCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => DB::table('permissions')->where('code', $permissionCode)->value('id'),
            ]);
        }

        return $user;
    }
}
