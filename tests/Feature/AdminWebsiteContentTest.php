<?php

namespace Tests\Feature;

use App\Models\AboutFaq;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_faq_routes_are_server_permission_gated(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'faqs.manage']);

        $this->actingAs($staff)->get(route('admin.homepage.index'))->assertForbidden();
        $this->get(route('admin.about-faqs.index'))->assertOk();
        $this->post(route('admin.about-faqs.store'), $this->faqData())->assertRedirect();
        $this->assertDatabaseCount('about_faqs', 1);
    }

    public function test_manager_can_initialize_and_manually_order_homepage_sections(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'homepage.manage']);
        $this->actingAs($manager)->get(route('admin.homepage.index'))->assertOk()->assertSee(__('admin.homepage.defaults_help'));
        $this->post(route('admin.homepage.initialize'))->assertRedirect(route('admin.homepage.index'));
        $this->assertDatabaseCount('product_collections', 2);
        $this->assertDatabaseCount('homepage_sections', 5);
        $sections = DB::table('homepage_sections')->orderBy('id')->get();
        $payload = ['sections' => $sections->map(fn ($section, $index) => [
            'id' => $section->id,
            'sort_order' => 100 - $index,
            'is_enabled' => $index === 0 ? '0' : '1',
        ])->all()];

        $this->patch(route('admin.homepage.sections.update'), $payload)->assertRedirect(route('admin.homepage.index'));
        $this->assertDatabaseHas('homepage_sections', ['id' => $sections[0]->id, 'sort_order' => 100, 'is_enabled' => false, 'updated_by' => $manager->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'homepage_section.updated', 'actor_user_id' => $manager->id]);
    }

    public function test_banner_upload_update_visibility_and_safe_link_validation(): void
    {
        Storage::fake('public');
        $manager = $this->createStaff(['dashboard.access', 'homepage.manage']);
        $this->actingAs($manager)->post(route('admin.homepage.banners.store'), $this->bannerData([
            'image' => UploadedFile::fake()->image('hero.webp'), 'target_url' => '/category/bedding',
        ]))->assertRedirect(route('admin.homepage.index'));
        $banner = DB::table('banners')->first();
        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'placement' => 'home_hero', 'is_active' => true, 'created_by' => $manager->id]);
        Storage::disk('public')->assertExists($banner->image_path);
        $oldPath = $banner->image_path;

        $this->put(route('admin.homepage.banners.update', $banner->id), $this->bannerData([
            'image' => UploadedFile::fake()->image('replacement.webp'), 'is_active' => '0', 'sort_order' => '2', 'target_url' => 'https://example.com/sale',
        ]))->assertRedirect(route('admin.homepage.index'));
        $updated = DB::table('banners')->find($banner->id);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->image_path);
        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'is_active' => false, 'sort_order' => 2, 'updated_by' => $manager->id]);

        $this->put(route('admin.homepage.banners.update', $banner->id), $this->bannerData(['image' => null, 'target_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('target_url');
        $this->put(route('admin.homepage.banners.update', $banner->id), $this->bannerData(['image' => null, 'target_url' => '//external.example']))
            ->assertSessionHasErrors('target_url');
    }

    public function test_new_and_popular_lists_only_use_manual_product_membership_and_order(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'homepage.manage']);
        $this->actingAs($manager)->post(route('admin.homepage.initialize'));
        $new = ProductCollection::query()->where('code', 'new')->firstOrFail();
        $popular = ProductCollection::query()->where('code', 'popular')->firstOrFail();
        $firstProduct = $this->publishedProduct('SET-A');
        $secondProduct = $this->publishedProduct('SET-B');

        $this->post(route('admin.homepage.collections.items.store', $new), ['product_id' => $firstProduct->id])->assertRedirect(route('admin.homepage.collections'));
        $this->post(route('admin.homepage.collections.items.store', $new), ['product_id' => $secondProduct->id])->assertRedirect(route('admin.homepage.collections'));
        $this->post(route('admin.homepage.collections.items.store', $new), ['product_id' => $secondProduct->id])->assertSessionHasErrors('product_id');
        $items = DB::table('product_collection_items')->where('product_collection_id', $new->id)->orderBy('position')->get();
        $this->patch(route('admin.homepage.collections.items.order', $new), ['items' => [
            ['id' => $items[0]->id, 'position' => 2], ['id' => $items[1]->id, 'position' => 1],
        ]])->assertRedirect(route('admin.homepage.collections'));
        $this->assertSame($secondProduct->id, (int) DB::table('product_collection_items')->where('product_collection_id', $new->id)->where('position', 1)->value('product_id'));
        $this->assertDatabaseCount('product_collection_items', 2);
        $this->assertDatabaseHas('activity_logs', ['event' => 'collection.reordered']);

        $this->post(route('admin.homepage.collections.items.store', $popular), ['product_id' => $firstProduct->id])->assertRedirect();
        $this->assertDatabaseHas('product_collection_items', ['product_collection_id' => $popular->id, 'product_id' => $firstProduct->id, 'position' => 1]);
        $this->delete(route('admin.homepage.collections.items.destroy', [$new, $items[0]->id]))->assertRedirect();
    }

    public function test_about_faqs_are_bilingual_moderated_by_visibility_and_soft_archived(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'faqs.manage']);
        $this->actingAs($manager)->get(route('admin.about-faqs.create'))->assertOk();
        $this->post(route('admin.about-faqs.store'), $this->faqData())->assertRedirect(route('admin.about-faqs.index'));
        $faq = AboutFaq::query()->with('translations')->firstOrFail();
        $this->assertSame('هل يوجد شحن؟', $faq->translations->firstWhere('locale', 'ar')->question);
        $this->assertSame('Do you deliver?', $faq->translations->firstWhere('locale', 'en')->question);

        $updated = $this->faqData(['is_active' => '0', 'sort_order' => '3', 'translations' => [
            'ar' => ['question' => 'هل يوجد استبدال؟', 'answer' => 'نعم'],
            'en' => ['question' => 'Can I exchange?', 'answer' => 'Yes'],
        ]]);
        $this->put(route('admin.about-faqs.update', $faq), $updated)->assertRedirect();
        $this->assertDatabaseHas('about_faqs', ['id' => $faq->id, 'is_active' => false, 'sort_order' => 3]);
        $this->delete(route('admin.about-faqs.archive', $faq))->assertRedirect();
        $this->assertSoftDeleted('about_faqs', ['id' => $faq->id]);
        $this->patch(route('admin.about-faqs.restore', $faq->id))->assertRedirect();
        $this->assertNotSoftDeleted('about_faqs', ['id' => $faq->id]);
        $this->assertFalse($faq->fresh()->is_active, 'Restoring an archived FAQ preserves its previous hidden state.');
        $this->assertDatabaseHas('activity_logs', ['event' => 'about_faq.archived', 'actor_user_id' => $manager->id]);
    }

    private function publishedProduct(string $sku): Product
    {
        $product = Product::query()->create([
            'product_type' => 'simple', 'sku' => $sku, 'slug' => strtolower($sku), 'status' => 'published',
            'store_availability_status' => 'available', 'requires_shipping' => true, 'published_at' => now(),
        ]);
        $product->translations()->createMany([
            ['locale' => 'ar', 'name' => 'منتج '.$sku],
            ['locale' => 'en', 'name' => 'Product '.$sku],
        ]);

        return $product;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function bannerData(array $overrides = []): array
    {
        return array_replace_recursive([
            'placement' => 'home_hero', 'sort_order' => '0', 'is_active' => '1', 'target_url' => '',
            'translations' => [
                'ar' => ['title' => 'عرض جديد', 'alt_text' => 'صورة عرض جديد'],
                'en' => ['title' => 'New offer', 'alt_text' => 'New offer image'],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function faqData(array $overrides = []): array
    {
        return array_replace_recursive([
            'sort_order' => '0', 'is_active' => '1',
            'translations' => [
                'ar' => ['question' => 'هل يوجد شحن؟', 'answer' => 'نعم، نوصل إلى محافظات مصر.'],
                'en' => ['question' => 'Do you deliver?', 'answer' => 'Yes, delivery is available across Egypt.'],
            ],
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
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $permission)->value('id')]);
        }

        return $user;
    }
}
