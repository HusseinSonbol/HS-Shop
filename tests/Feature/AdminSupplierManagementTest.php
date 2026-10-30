<?php

namespace Tests\Feature;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_routes_require_supplier_management_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.suppliers.index'))->assertForbidden();
    }

    public function test_manager_can_create_update_archive_and_restore_supplier(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'suppliers.manage']);
        $this->actingAs($manager);

        $this->get(route('admin.suppliers.index'))->assertOk()->assertSee(__('admin.suppliers.title'))->assertSee('btn btn-primary primary-button');
        $this->get(route('admin.suppliers.create'))->assertOk()->assertSee('btn btn-primary primary-button')->assertSee('name="address[governorate]"', false);
        $this->post(route('admin.suppliers.store'), $this->supplierData())
            ->assertRedirect();
        $supplier = Supplier::query()->where('code', 'FACTORY-01')->firstOrFail();
        $this->assertSame(['governorate' => 'Cairo', 'city' => 'Nasr City'], $supplier->address_json);
        $this->assertSame('Factory owner', $supplier->owner_name);
        $this->assertSame('01012345678', $supplier->owner_phone);
        $this->assertSame('Contact person', $supplier->contact_name);
        $this->assertSame('0222222222', $supplier->phone);
        $this->assertDatabaseHas('activity_logs', ['event' => 'supplier.created', 'subject_id' => $supplier->id]);

        $supplier->update(['address_json' => ['country_code' => 'EG'] + $supplier->address_json]);
        $this->get(route('admin.suppliers.edit', $supplier))->assertOk()->assertSee('name="address[city]"', false)->assertSee('Nasr City');
        $this->put(route('admin.suppliers.update', $supplier), $this->supplierData(['name' => 'Updated factory', 'address' => ['governorate' => 'Cairo', 'city' => 'New Cairo']]))
            ->assertRedirect(route('admin.suppliers.show', $supplier));
        $this->assertSame('Updated factory', $supplier->fresh()->name);
        $this->assertSame(['country_code' => 'EG', 'governorate' => 'Cairo', 'city' => 'New Cairo'], $supplier->fresh()->address_json);
        $this->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee('New Cairo')->assertSee('Factory owner')->assertSee('01012345678')->assertSee('Contact person');
        $this->assertDatabaseHas('activity_logs', ['event' => 'supplier.updated', 'subject_id' => $supplier->id]);

        $this->delete(route('admin.suppliers.archive', $supplier))->assertRedirect(route('admin.suppliers.index'));
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->patch(route('admin.suppliers.restore', $supplier))->assertRedirect();
        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertTrue($supplier->fresh()->is_active);
    }

    public function test_product_can_have_multiple_supplier_offers_and_changes_are_versioned_with_actor(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'suppliers.manage']);
        $this->actingAs($manager);
        $supplierA = Supplier::create($this->supplierAttributes('FACTORY-A'));
        $supplierB = Supplier::create($this->supplierAttributes('SHOWROOM-B', 'showroom'));
        $productId = $this->createProduct();

        $this->get(route('admin.suppliers.offers.create', $supplierA))->assertOk()->assertSee('product_id')->assertSee('btn btn-primary primary-button');
        $this->post(route('admin.suppliers.offers.store', $supplierA), $this->offerData($productId))
            ->assertRedirect();
        $offerA = ProductSupplier::query()->where('supplier_id', $supplierA->id)->firstOrFail();
        $this->post(route('admin.suppliers.offers.store', $supplierB), $this->offerData($productId, [
            'unit_cost' => '220.0000',
            'supplier_availability_status' => 'available',
        ]))->assertRedirect();
        $offerB = ProductSupplier::query()->where('supplier_id', $supplierB->id)->firstOrFail();

        $this->assertSame($productId, $offerA->product_id);
        $this->assertSame($productId, $offerB->product_id);
        $this->assertCount(2, ProductSupplier::query()->where('product_id', $productId)->get());
        $this->assertDatabaseHas('product_supplier_history', [
            'product_supplier_id' => $offerA->id,
            'version_no' => 1,
            'unit_cost' => '125.1250',
            'changed_by' => $manager->id,
        ]);

        $this->put(route('admin.suppliers.offers.update', [$supplierA, $offerA]), $this->offerData($productId, [
            'unit_cost' => '135.1299',
            'supplier_availability_status' => 'unavailable',
            'valid_from' => '2026-10-29T10:00',
            'valid_until' => '2026-11-02T10:00',
            'change_reason' => 'Supplier changed quote',
        ]))->assertRedirect();

        $this->assertSame('135.1299', $offerA->fresh()->unit_cost);
        $this->assertDatabaseHas('product_supplier_history', [
            'product_supplier_id' => $offerA->id,
            'version_no' => 2,
            'unit_cost' => '135.1299',
            'supplier_availability_status' => 'unavailable',
            'changed_by' => $manager->id,
            'change_reason' => 'Supplier changed quote',
        ]);
        $this->get(route('admin.suppliers.offers.edit', [$supplierA, $offerA]))
            ->assertOk()->assertSee('135.1299')->assertSee('Supplier changed quote')->assertSee($manager->name);
        $this->get(route('admin.suppliers.show', $supplierA))->assertOk()->assertSee('135.1299');

        $this->put(route('admin.suppliers.offers.update', [$supplierA, $offerA]), $this->offerData($productId, [
            'unit_cost' => '135.1299',
            'supplier_availability_status' => 'unavailable',
            'valid_from' => '2026-10-29T10:00',
            'valid_until' => '2026-11-02T10:00',
        ]))->assertRedirect();
        $this->assertDatabaseCount('product_supplier_history', 3);
        $this->assertDatabaseHas('activity_logs', ['event' => 'supplier_offer.updated', 'subject_id' => $offerA->id]);
    }

    public function test_duplicate_supplier_product_link_and_invalid_offer_dates_are_rejected(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'suppliers.manage']);
        $this->actingAs($manager);
        $supplier = Supplier::create($this->supplierAttributes('FACTORY-C'));
        $productId = $this->createProduct();
        $this->post(route('admin.suppliers.offers.store', $supplier), $this->offerData($productId))->assertRedirect();
        $this->post(route('admin.suppliers.offers.store', $supplier), $this->offerData($productId))->assertSessionHasErrors('product_id');

        $otherSupplier = Supplier::create($this->supplierAttributes('FACTORY-D'));
        $this->post(route('admin.suppliers.offers.store', $otherSupplier), $this->offerData($productId, [
            'valid_from' => '2026-11-02T10:00',
            'valid_until' => '2026-10-29T10:00',
        ]))->assertSessionHasErrors('valid_until');
        $this->post(route('admin.suppliers.store'), $this->supplierData(['code' => 'FACTORY-ADDR', 'address' => ['city' => str_repeat('x', 121)]]))
            ->assertSessionHasErrors('address.city');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function supplierData(array $overrides = []): array
    {
        return array_replace([
            'code' => 'FACTORY-01', 'supplier_type' => 'factory', 'name' => 'Sample factory',
            'owner_name' => 'Factory owner', 'owner_phone' => '01012345678',
            'contact_name' => 'Contact person', 'email' => 'factory@example.test', 'phone' => '0222222222',
            'address' => ['governorate' => 'Cairo', 'city' => 'Nasr City'], 'notes' => 'Supplier notes', 'is_active' => '1',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function supplierAttributes(string $code, string $type = 'factory'): array
    {
        return ['code' => $code, 'supplier_type' => $type, 'name' => $code, 'is_active' => true];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function offerData(int $productId, array $overrides = []): array
    {
        return array_replace([
            'product_id' => $productId,
            'unit_cost' => '125.1250',
            'supplier_availability_status' => 'unknown',
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => '1',
            'change_reason' => '',
        ], $overrides);
    }

    private function createProduct(): int
    {
        $now = now();
        $productId = DB::table('products')->insertGetId([
            'product_type' => 'simple', 'sku' => 'TEST-'.fake()->unique()->numerify('#####'), 'slug' => 'test-product-'.fake()->unique()->numerify('#####'),
            'status' => 'draft', 'store_availability_status' => 'unavailable', 'requires_shipping' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('product_translations')->insert([
            'product_id' => $productId, 'locale' => 'en', 'name' => 'Test product', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $productId;
    }

    /** @param list<string> $permissions */
    private function createStaff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('roles')->insertOrIgnore(['code' => 'manager', 'name' => 'Manager', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->where('code', 'manager')->value('id');
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => DB::table('permissions')->where('code', $permission)->value('id'),
            ]);
        }

        return $user;
    }
}
