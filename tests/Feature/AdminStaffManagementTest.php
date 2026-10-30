<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\StaffManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_users_manage_permission_can_open_staff_management(): void
    {
        $operator = $this->createStaff('operator', ['dashboard.access']);

        $this->actingAs($operator)->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_owner_can_create_and_update_staff_with_server_assigned_roles(): void
    {
        $owner = $this->createStaff('super_admin', ['dashboard.access', 'users.manage']);
        $this->actingAs($owner);

        $this->post(route('admin.staff.store'), [
            'name' => 'Operations One',
            'email' => 'ops@example.test',
            'phone' => '+201000000000',
            'password' => 'a-strong-password-123',
            'role' => 'operator',
            'is_active' => '1',
        ])->assertRedirect(route('admin.staff.index'));

        $operator = User::query()->where('email', 'ops@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-password-123', $operator->password));
        $this->assertSame('operator', $operator->roles()->value('roles.code'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'staff.created', 'subject_id' => $operator->id]);

        $this->put(route('admin.staff.update', $operator), [
            'name' => 'Operations Lead',
            'email' => 'ops@example.test',
            'phone' => '',
            'password' => '',
            'role' => 'manager',
            'is_active' => '1',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertSame('Operations Lead', $operator->fresh()->name);
        $this->assertSame('manager', $operator->fresh()->roles()->value('roles.code'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'staff.updated', 'subject_id' => $operator->id]);
    }

    public function test_staff_password_must_be_at_least_nine_characters(): void
    {
        $owner = $this->createStaff('super_admin', ['dashboard.access', 'users.manage']);
        $this->actingAs($owner);

        $this->from(route('admin.staff.create'))
            ->post(route('admin.staff.store'), [
                'name' => 'Short Password',
                'email' => 'short@example.test',
                'password' => '12345678',
                'role' => 'operator',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.staff.create'))
            ->assertSessionHasErrors('password');

        $this->post(route('admin.staff.store'), [
            'name' => 'Nine Chars',
            'email' => 'nine@example.test',
            'password' => '123456789',
            'role' => 'operator',
            'is_active' => '1',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('users', ['email' => 'nine@example.test']);
    }

    public function test_staff_list_and_forms_render_and_accounts_can_be_disabled_and_archived(): void
    {
        $owner = $this->createStaff('super_admin', ['dashboard.access', 'users.manage']);
        $operator = $this->createStaff('operator', ['dashboard.access']);
        $this->actingAs($owner);

        $this->get(route('admin.staff.index'))->assertOk()->assertSee('Admin staff')->assertSee($operator->email);
        $this->get(route('admin.staff.create'))->assertOk()->assertSee('Operations staff')->assertSee('Role permission summary');
        $this->get(route('admin.staff.edit', $operator))->assertOk()->assertSee($operator->email);

        $this->patch(route('admin.staff.active', $operator))->assertRedirect();
        $this->assertFalse($operator->fresh()->is_active);

        $this->delete(route('admin.staff.destroy', $operator))->assertRedirect(route('admin.staff.index'));
        $this->assertSoftDeleted('users', ['id' => $operator->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'staff.archived', 'subject_id' => $operator->id]);
    }

    public function test_language_switcher_preserves_the_admin_page_and_saves_the_preference(): void
    {
        $owner = $this->createStaff('super_admin', ['dashboard.access', 'users.manage']);
        $this->actingAs($owner)->from(route('admin.staff.index'))
            ->post(route('locale.update', 'ar'))
            ->assertRedirect(route('admin.staff.index'));

        $this->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'preferred_locale' => 'ar']);

        $this->from(route('admin.staff.index'))
            ->post(route('locale.update', 'en'))
            ->assertRedirect(route('admin.staff.index'));
        $this->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);
    }

    public function test_deactivating_a_signed_in_staff_member_blocks_the_next_dashboard_request(): void
    {
        $staff = $this->createStaff('manager', ['dashboard.access']);
        $this->post(route('admin.login.store'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        DB::table('users')->where('id', $staff->id)->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'هذا الحساب غير نشط. تواصل مع مالك المتجر.');
        $this->assertGuest();
        $this->get(route('admin.login'))->assertSee('هذا الحساب غير نشط. تواصل مع مالك المتجر.');
    }

    public function test_last_active_super_admin_cannot_be_disabled_downgraded_or_archived(): void
    {
        $owner = $this->createStaff('super_admin', ['dashboard.access', 'users.manage']);
        $service = app(StaffManagementService::class);

        try {
            $service->setActive($owner, false);
            $this->fail('Expected the last super admin disable to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff', $exception->errors());
        }

        try {
            $service->update($owner, [
                'name' => $owner->name,
                'email' => $owner->email,
                'password' => null,
                'role' => 'manager',
                'is_active' => true,
            ]);
            $this->fail('Expected the last super admin role downgrade to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff', $exception->errors());
        }

        try {
            $service->archive($owner);
            $this->fail('Expected the last super admin archive to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff', $exception->errors());
        }

        $this->assertTrue($owner->fresh()->is_active);
        $this->assertNull($owner->fresh()->deleted_at);
        $this->assertSame('super_admin', $owner->fresh()->roles()->value('roles.code'));
    }

    public function test_database_seeder_installs_the_approved_role_permission_matrix(): void
    {
        $originalEmail = getenv('HS_SHOP_ADMIN_EMAIL');
        $originalPassword = getenv('HS_SHOP_ADMIN_PASSWORD');
        putenv('HS_SHOP_ADMIN_EMAIL=seeder-owner@example.test');
        putenv('HS_SHOP_ADMIN_PASSWORD=seeder-test-password-123');
        $_ENV['HS_SHOP_ADMIN_EMAIL'] = 'seeder-owner@example.test';
        $_ENV['HS_SHOP_ADMIN_PASSWORD'] = 'seeder-test-password-123';

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            $originalEmail === false ? putenv('HS_SHOP_ADMIN_EMAIL') : putenv('HS_SHOP_ADMIN_EMAIL='.$originalEmail);
            $originalPassword === false ? putenv('HS_SHOP_ADMIN_PASSWORD') : putenv('HS_SHOP_ADMIN_PASSWORD='.$originalPassword);
            unset($_ENV['HS_SHOP_ADMIN_EMAIL'], $_ENV['HS_SHOP_ADMIN_PASSWORD']);
        }

        $this->assertDatabaseHas('roles', ['code' => 'manager']);
        $this->assertDatabaseHas('roles', ['code' => 'operator']);
        $operatorPermissions = DB::table('role_permission')
            ->join('roles', 'roles.id', '=', 'role_permission.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('roles.code', 'operator')
            ->pluck('permissions.code')
            ->all();

        $this->assertContains('orders.manage', $operatorPermissions);
        $this->assertContains('support_tickets.manage', $operatorPermissions);
        $this->assertNotContains('customers.manage', $operatorPermissions);
        $this->assertNotContains('reviews.moderate', $operatorPermissions);
    }

    private function createStaff(string $roleCode, array $permissionCodes): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['super_admin', 'manager', 'operator'] as $availableRoleCode) {
            DB::table('roles')->insertOrIgnore([
                'code' => $availableRoleCode,
                'name' => ucfirst(str_replace('_', ' ', $availableRoleCode)),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
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
            $permissionId = DB::table('permissions')->where('code', $permissionCode)->value('id');
            DB::table('role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        return $user;
    }
}
