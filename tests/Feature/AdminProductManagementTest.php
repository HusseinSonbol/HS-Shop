<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_routes_require_catalog_management_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_staff_can_create_simple_product_with_secure_image_and_manual_price(): void
    {
        Storage::fake('public');
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();

        $this->actingAs($manager)->get(route('admin.products.index'))->assertOk()->assertSee(__('admin.products.title'));
        $this->post(route('admin.products.store'), $this->productData($category, [
            'images' => [UploadedFile::fake()->image('product.jpg'), UploadedFile::fake()->image('alternate.jpg')],
        ]))->assertRedirect();

        $product = Product::query()->with(['variants', 'images', 'translations'])->where('sku', 'BED-SET')->firstOrFail();
        $this->get(route('admin.products.show', $product))->assertOk()->assertSee('BED-SET-01')->assertSee('مجموعة سرير');
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="variants[0][sku]"', false);
        $variant = $product->variants->sole();
        $this->assertSame('BED-SET-01', $variant->sku);
        $this->assertSame('1250.0000', $variant->current_regular_price);
        $this->assertSame('999.0000', $variant->current_sale_price);
        $this->assertSame('مجموعة سرير', $product->translations->firstWhere('locale', 'ar')->name);
        $this->assertSame('BED-SET', $product->sku, 'The product SKU is the family/group code.');
        $this->assertSame(1, $product->categories()->count());
        $this->assertSame(2, $product->images->count());
        $this->assertTrue($product->images->first()->is_primary);
        $this->assertFalse($product->images->last()->is_primary);
        $this->assertSame('BED-SET', $product->sku);
        Storage::disk('public')->assertExists($product->images->first()->path);
        Storage::disk('public')->assertExists($product->images->last()->path);
        $selectedImage = $product->images->last();
        $updateData = $this->productData($category, [
            'primary_image_id' => (string) $selectedImage->id,
            'variants' => [$this->variantData('BED-SET-ONE', ['id' => $variant->id])],
        ]);
        $this->put(route('admin.products.update', $product), $updateData)->assertRedirect();
        $this->assertDatabaseHas('product_images', ['id' => $selectedImage->id, 'is_primary' => true]);
        $this->assertDatabaseHas('product_images', ['id' => $product->images->first()->id, 'is_primary' => false]);
        $this->assertDatabaseHas('product_availability_history', [
            'product_id' => $product->id,
            'availability_status' => 'available',
            'checked_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'product.created', 'subject_id' => $product->id]);
        $this->assertDatabaseHas('product_price_history', [
            'product_variant_id' => $variant->id,
            'version_no' => 1,
            'factory_unit_cost' => '500.0000',
            'inbound_delivery_unit_cost' => '10.0000',
            'packaging_unit_cost' => '15.0000',
            'advertising_unit_cost' => '5.0000',
            'pricing_cost_total' => '530.0000',
            'profit_rate_percent' => '135.8491',
            'official_regular_price' => '1250.0000',
            'sale_price' => '999.0000',
            'calculated_regular_price' => null,
            'recorded_by' => $manager->id,
            'change_reason' => null,
        ]);
    }

    public function test_variable_products_keep_variant_skus_prices_and_translated_attributes(): void
    {
        Storage::fake('public');
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $data = $this->productData($category, [
            'product_type' => 'variable',
            'sku' => 'TOWEL-SET',
            'slug' => 'towel-set',
            'translations' => [
                'ar' => ['name' => 'طقم مناشف'],
                'en' => ['name' => 'Towel set'],
            ],
            'variants' => [
                $this->variantData('TOWEL-SMALL', ['size_ar' => 'صغير', 'size_en' => 'Small', 'is_default' => '1']),
                $this->variantData('TOWEL-LARGE', ['size_ar' => 'كبير', 'size_en' => 'Large', 'current_regular_price' => '700', 'current_sale_price' => '', 'is_default' => '0']),
            ],
        ]);

        $this->actingAs($manager)->post(route('admin.products.store'), $data)->assertRedirect();
        $product = Product::query()->where('sku', 'TOWEL-SET')->firstOrFail();
        $this->assertSame(2, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'TOWEL-SET-LARGE', 'current_regular_price' => '700.0000', 'is_default' => false]);
        $this->assertDatabaseHas('attribute_value_translations', ['locale' => 'en', 'value' => 'Large']);
        $this->assertDatabaseHas('attribute_value_translations', ['locale' => 'ar', 'value' => 'كبير']);
        $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count());
    }

    public function test_product_update_audits_availability_changes_and_archive_is_reversible(): void
    {
        Storage::fake('public');
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $this->actingAs($manager)->post(route('admin.products.store'), $this->productData($category))->assertRedirect();
        $product = Product::query()->where('sku', 'BED-SET')->firstOrFail();
        $checkedAt = $product->availability_checked_at;

        $updatedData = $this->productData($category, [
            'translations' => ['ar' => ['name' => 'مجموعة سرير محدثة'], 'en' => ['name' => 'Updated bed set']],
            'variants' => [$this->variantData('BED-SET-ONE', ['id' => $product->variants()->value('id')])],
        ]);
        $this->put(route('admin.products.update', $product), $updatedData)->assertRedirect(route('admin.products.show', $product));
        $this->assertSame(1, DB::table('product_availability_history')->where('product_id', $product->id)->count());
        $this->assertEquals($checkedAt, $product->fresh()->availability_checked_at);

        $updatedData = $this->productData($category, [
            'store_availability_status' => 'unavailable',
            'variants' => [$this->variantData('BED-SET-ONE', ['id' => $product->variants()->value('id')])],
        ]);
        $this->put(route('admin.products.update', $product), $updatedData)->assertRedirect();
        $this->assertDatabaseCount('product_availability_history', 2);
        $this->assertDatabaseHas('product_availability_history', ['product_id' => $product->id, 'availability_status' => 'unavailable']);

        $this->delete(route('admin.products.archive', $product))->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id]);
        $this->patch(route('admin.products.restore', $product))->assertRedirect();
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_invalid_sale_price_and_inactive_category_are_rejected(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $invalid = $this->productData($category, [
            'variants' => [$this->variantData('BAD-SALE', ['current_sale_price' => '1300'])],
        ]);

        $this->actingAs($manager)->post(route('admin.products.store'), $invalid)
            ->assertSessionHasErrors('variants.0.current_sale_price');
        $category->update(['is_active' => false]);
        $this->post(route('admin.products.store'), $this->productData($category))
            ->assertSessionHasErrors('category_ids.0');
    }

    public function test_zero_total_cost_is_rejected_for_markup_calculation(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $variant = $this->variantData('IGNORED', [
            'factory_unit_cost' => '0', 'inbound_delivery_unit_cost' => '0',
            'packaging_unit_cost' => '0', 'advertising_unit_cost' => '0',
        ]);

        $this->actingAs($manager)->post(route('admin.products.store'), $this->productData($category, ['variants' => [$variant]]))
            ->assertSessionHasErrors('variants.0.factory_unit_cost');
    }

    public function test_duplicate_size_and_color_combinations_are_rejected(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $data = $this->productData($category, [
            'product_type' => 'variable',
            'variants' => [
                $this->variantData('DUP-S', ['size_ar' => 'صغير', 'size_en' => 'Small']),
                $this->variantData('DUP-S-2', ['size_ar' => 'صغير', 'size_en' => 'Small', 'is_default' => '0']),
            ],
        ]);

        $this->actingAs($manager)->post(route('admin.products.store'), $data)
            ->assertSessionHasErrors('variants.1.size_ar');
    }

    public function test_group_code_and_slug_are_generated_from_english_name_with_unique_suffix(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $first = $this->productData($category, [
            'sku' => 'IGNORED-CODE', 'slug' => 'ignored-slug',
            'translations' => ['ar' => ['name' => 'طقم قطني'], 'en' => ['name' => 'Cotton set']],
            'variants' => [$this->variantData('COTTON-V1')],
        ]);
        $second = array_replace($first, ['variants' => [$this->variantData('COTTON-V2')]]);

        $this->actingAs($manager)->post(route('admin.products.store'), $first)->assertRedirect();
        $this->post(route('admin.products.store'), $second)->assertRedirect();
        $this->assertDatabaseHas('products', ['sku' => 'COTTON-SET', 'slug' => 'cotton-set']);
        $this->assertDatabaseHas('products', ['sku' => 'COTTON-SET-2', 'slug' => 'cotton-set-2']);
    }

    public function test_nested_category_path_is_explained_and_parent_categories_are_linked_automatically(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $parent = $this->createCategory('clothing', 'ملابس', 'Clothing');
        $child = $this->createCategory('homewear', 'ملابس بيتي', 'Homewear', $parent);
        $this->actingAs($manager);

        $this->get(route('admin.products.create'))->assertOk()->assertSee('Clothing / Homewear');
        $this->post(route('admin.products.store'), $this->productData($child))->assertRedirect();
        $product = Product::query()->where('sku', 'BED-SET')->firstOrFail();
        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $product->categories()->pluck('categories.id')->all());
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="category_ids[]" value="'.$child->id.'" checked', false);
    }

    public function test_price_changes_append_a_version_and_keep_the_previous_snapshot_unchanged(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $this->actingAs($manager)->post(route('admin.products.store'), $this->productData($category))->assertRedirect();
        $product = Product::query()->where('sku', 'BED-SET')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $versionOne = ProductPriceHistory::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $updateData = $this->productData($category, [
            'variants' => [$this->variantData('BED-SET-ONE', [
                'id' => $variant->id,
                'current_regular_price' => '1300',
                'current_sale_price' => '1100',
                'factory_unit_cost' => '540',
                'change_reason' => 'Supplier price changed',
            ])],
        ]);

        $this->put(route('admin.products.update', $product), $updateData)->assertRedirect();
        $versionTwo = ProductPriceHistory::query()->where('product_variant_id', $variant->id)->where('version_no', 2)->firstOrFail();
        $this->assertSame('1250.0000', $versionOne->official_regular_price);
        $this->assertSame('999.0000', $versionOne->sale_price);
        $this->assertSame('1300.0000', $versionTwo->official_regular_price);
        $this->assertSame('1100.0000', $versionTwo->sale_price);
        $this->assertSame('540.0000', $versionTwo->factory_unit_cost);
        $this->assertSame('570.0000', $versionTwo->pricing_cost_total);
        $this->assertSame('128.0702', $versionTwo->profit_rate_percent);
        $this->assertSame('Supplier price changed', $versionTwo->change_reason);
        $this->assertSame($manager->id, $versionTwo->recorded_by);
        $this->assertNotNull($versionTwo->recorded_at);
        $this->assertSame($versionTwo->id, $variant->fresh()->current_price_version_id);
        $this->assertSame(2, DB::table('product_price_history')->where('product_variant_id', $variant->id)->count());
        $this->get(route('admin.products.show', $product))->assertOk()->assertSee('Supplier price changed')->assertSee('530.0000')->assertSee('570.0000');
    }

    public function test_price_change_requires_cost_inputs_when_no_prior_version_and_requires_reason(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $category = $this->createCategory();
        $this->actingAs($manager)->post(route('admin.products.store'), $this->productData($category))->assertRedirect();
        $product = Product::query()->where('sku', 'BED-SET')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        DB::table('product_variants')->where('id', $variant->id)->update(['current_price_version_id' => null]);
        DB::table('product_price_history')->where('product_variant_id', $variant->id)->delete();
        $updateData = $this->productData($category, [
            'variants' => [$this->variantData('BED-SET-ONE', [
                'id' => $variant->id,
                'current_regular_price' => '1400',
                'factory_unit_cost' => '', 'inbound_delivery_unit_cost' => '', 'packaging_unit_cost' => '',
                'advertising_unit_cost' => '', 'profit_rate_percent' => '', 'change_reason' => '',
            ])],
        ]);

        $this->put(route('admin.products.update', $product), $updateData)
            ->assertSessionHasErrors(['variants.0.factory_unit_cost', 'variants.0.change_reason']);
    }

    private function createCategory(string $slug = 'bedding', string $arabic = 'مفروشات', string $english = 'Bedding', ?Category $parent = null): Category
    {
        $category = Category::create(['parent_id' => $parent?->id, 'slug' => $slug, 'sort_order' => 0, 'is_active' => true]);
        $category->translations()->createMany([
            ['locale' => 'ar', 'name' => $arabic, 'description' => null],
            ['locale' => 'en', 'name' => $english, 'description' => null],
        ]);

        return $category;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function productData(Category $category, array $overrides = []): array
    {
        $defaults = [
            'product_type' => 'simple', 'sku' => 'BED-SET', 'slug' => 'bed-set', 'brand_id' => '',
            'status' => 'draft', 'store_availability_status' => 'available', 'requires_shipping' => '1',
            'weight_g' => '1000', 'category_ids' => [(string) $category->id],
            'translations' => ['ar' => ['name' => 'مجموعة سرير'], 'en' => ['name' => 'Bed set']],
            'variants' => [$this->variantData('BED-SET-ONE')],
        ];

        return array_replace($defaults, $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function variantData(string $sku, array $overrides = []): array
    {
        return array_replace([
            'sku' => $sku, 'barcode' => '', 'variant_name' => '', 'size_ar' => '', 'size_en' => '', 'color_ar' => '', 'color_en' => '',
            'current_regular_price' => '1250', 'current_sale_price' => '999', 'factory_unit_cost' => '500',
            'inbound_delivery_unit_cost' => '10', 'packaging_unit_cost' => '15', 'advertising_unit_cost' => '5',
            'profit_rate_percent' => '9999', 'change_reason' => '',
            'weight_g' => '', 'is_active' => '1', 'is_default' => '1',
        ], $overrides);
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
