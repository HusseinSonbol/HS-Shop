<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_routes_require_catalog_permission(): void
    {
        $staff = $this->createStaff(['dashboard.access']);

        $this->actingAs($staff)->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_manager_can_create_edit_and_find_bilingual_nested_categories(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $this->actingAs($manager);

        $this->get(route('admin.categories.index'))->assertOk()->assertSee(__('admin.categories.title'))->assertSee('btn btn-primary primary-button');
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('translations[ar][name]')->assertSee('btn btn-primary primary-button');
        $this->post(route('admin.categories.store'), $this->categoryData('clothing', 'ملابس', 'Clothing'))
            ->assertRedirect(route('admin.categories.index'));
        $parent = Category::query()->where('slug', 'clothing')->firstOrFail();
        $this->post(route('admin.categories.store'), $this->categoryData('shirts', 'قمصان', 'Shirts', $parent->id, 3))
            ->assertRedirect(route('admin.categories.index'));
        $child = Category::query()->where('slug', 'shirts')->firstOrFail();

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('قمصان', $child->translations()->where('locale', 'ar')->value('name'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'category.created', 'subject_id' => $child->id]);
        $this->get(route('admin.categories.index', ['q' => 'قمصان']))->assertOk()->assertSee('shirts');
        $this->get(route('admin.categories.edit', $child))->assertOk()->assertSee('قمصان');

        $this->put(route('admin.categories.update', $child), $this->categoryData('shirts', 'قمصان محدثة', 'Updated shirts', $parent->id, 1))
            ->assertRedirect(route('admin.categories.index'));
        $this->assertSame('قمصان محدثة', $child->fresh()->translations()->where('locale', 'ar')->value('name'));
        $this->assertSame(1, $child->fresh()->sort_order);
        $this->assertDatabaseHas('activity_logs', ['event' => 'category.updated', 'subject_id' => $child->id]);
    }

    public function test_parent_cannot_be_self_or_descendant_and_archived_categories_are_not_selectable(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $this->actingAs($manager);
        $this->post(route('admin.categories.store'), $this->categoryData('parent', 'رئيسي', 'Parent'))->assertRedirect();
        $parent = Category::query()->where('slug', 'parent')->firstOrFail();
        $this->post(route('admin.categories.store'), $this->categoryData('child', 'فرعي', 'Child', $parent->id))->assertRedirect();
        $child = Category::query()->where('slug', 'child')->firstOrFail();

        $this->put(route('admin.categories.update', $parent), $this->categoryData('parent', 'رئيسي', 'Parent', $parent->id))
            ->assertSessionHasErrors('parent_id');
        $this->put(route('admin.categories.update', $parent), $this->categoryData('parent', 'رئيسي', 'Parent', $child->id))
            ->assertSessionHasErrors('parent_id');
        $this->put(route('admin.categories.update', $parent), $this->categoryData('parent', 'رئيسي', 'Parent', null, 0, false))
            ->assertSessionHasErrors('category');
        $this->delete(route('admin.categories.archive', $parent))->assertSessionHasErrors('category');

        $this->delete(route('admin.categories.archive', $child))->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $child->id]);
        $this->assertFalse(Category::availableForSelection()->whereKey($child->id)->exists());
        $this->get(route('admin.categories.create'))->assertOk()->assertDontSee('value="'.$child->id.'"', false);
        $this->patch(route('admin.categories.restore', $child))->assertRedirect();
        $this->assertNotSoftDeleted('categories', ['id' => $child->id]);
    }

    public function test_inactive_category_cannot_be_selected_as_parent_and_archived_parent_restores_last(): void
    {
        $manager = $this->createStaff(['dashboard.access', 'catalog.manage']);
        $this->actingAs($manager);
        $this->post(route('admin.categories.store'), $this->categoryData('inactive', 'غير نشط', 'Inactive', null, 0, false))->assertRedirect();
        $inactive = Category::query()->where('slug', 'inactive')->firstOrFail();
        $this->post(route('admin.categories.store'), $this->categoryData('child', 'فرعي', 'Child', $inactive->id))
            ->assertSessionHasErrors('parent_id');

        $this->delete(route('admin.categories.archive', $inactive))->assertRedirect();
        $this->patch(route('admin.categories.restore', $inactive))->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['event' => 'category.archived', 'subject_id' => $inactive->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'category.restored', 'subject_id' => $inactive->id]);
    }

    /** @return array<string, mixed> */
    private function categoryData(string $slug, string $arabic, string $english, ?int $parentId = null, int $order = 0, bool $active = true): array
    {
        return [
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => $order,
            'is_active' => $active ? '1' : '0',
            'translations' => [
                'ar' => ['name' => $arabic, 'description' => 'وصف'],
                'en' => ['name' => $english, 'description' => 'Description'],
            ],
        ];
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
