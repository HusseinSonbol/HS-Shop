<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_sign_in_and_sees_localized_dashboard_and_account_controls(): void
    {
        $user = $this->createAdmin();

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee($user->name)
            ->assertSee(route('admin.logout'));

        $this->post(route('locale.update', 'en'))
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Store overview');
    }

    public function test_inactive_user_cannot_sign_in_or_access_dashboard(): void
    {
        $user = $this->createAdmin(['is_active' => false]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_active_user_without_dashboard_permission_is_denied(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->withSession(['locale' => 'en'])
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Access denied');
    }

    public function test_dashboard_shows_permitted_customer_supplier_and_category_summaries(): void
    {
        $user = $this->createAdmin();
        $roleId = DB::table('user_role')->where('user_id', $user->id)->value('role_id');
        foreach (['customers.manage', 'suppliers.manage', 'catalog.manage'] as $code) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('customers')->insert([
            'customer_number' => 'CUS-DASH-1', 'display_name' => 'Dashboard customer', 'phone' => '01000000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('suppliers')->insert([
            'code' => 'SUP-DASH-1', 'supplier_type' => 'factory', 'name' => 'Dashboard supplier', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'slug' => 'dashboard-category', 'sort_order' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.dashboard.module_summary'))
            ->assertSee(__('admin.dashboard.customers'))
            ->assertSee(__('admin.dashboard.suppliers'))
            ->assertSee(__('admin.dashboard.categories'))
            ->assertSee(route('admin.customers.index'))
            ->assertSee(route('admin.suppliers.index'))
            ->assertSee(route('admin.categories.index'));
    }

    public function test_dashboard_hides_module_summaries_when_user_lacks_module_permissions(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.customers.index'))
            ->assertDontSee(route('admin.suppliers.index'))
            ->assertDontSee(route('admin.categories.index'));
    }

    public function test_locale_switcher_rejects_an_external_referer(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)
            ->from('https://attacker.example/redirect')
            ->post(route('locale.update', 'en'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout_is_post_only_and_invalidates_authenticated_session(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)->withSession(['locale' => 'en']);
        $this->get(route('admin.logout'))->assertMethodNotAllowed();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'You have signed out safely.')
            ->assertSessionMissing('locale');

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    private function createAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId([
            'code' => 'test-admin-'.$user->id,
            'name' => 'Test admin',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'code' => 'dashboard.access',
            'description' => 'Access admin dashboard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'assigned_by' => null,
            'created_at' => now(),
        ]);
        DB::table('role_permission')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        return $user;
    }
}
