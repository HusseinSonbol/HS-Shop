<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = trim((string) env('HS_SHOP_ADMIN_EMAIL'));
        $password = (string) env('HS_SHOP_ADMIN_PASSWORD');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Set a valid HS_SHOP_ADMIN_EMAIL in the environment before seeding.');
        }

        if (strlen($password) < 9) {
            throw new RuntimeException('Set HS_SHOP_ADMIN_PASSWORD to a unique password of at least 9 characters before seeding.');
        }

        $permissionCodes = [
            'dashboard.access', 'users.manage', 'customers.manage', 'roles.manage',
            'catalog.manage', 'prices.manage', 'suppliers.manage', 'homepage.manage',
            'faqs.manage', 'orders.manage', 'order_revisions.manage', 'payments.manage',
            'shipments.manage', 'returns.manage', 'refunds.manage', 'expenses.manage',
            'reviews.moderate', 'feedback.moderate', 'contact_messages.manage',
            'support_tickets.manage', 'reports.view', 'settings.manage', 'activity_logs.view',
        ];

        $rolePermissions = [
            'manager' => [
                'dashboard.access', 'customers.manage', 'catalog.manage', 'prices.manage',
                'suppliers.manage', 'homepage.manage', 'faqs.manage', 'orders.manage',
                'order_revisions.manage', 'payments.manage', 'shipments.manage', 'returns.manage',
                'refunds.manage', 'expenses.manage', 'reviews.moderate', 'feedback.moderate',
                'contact_messages.manage', 'support_tickets.manage', 'reports.view',
            ],
            'operator' => [
                'dashboard.access', 'orders.manage', 'payments.manage', 'shipments.manage',
                'returns.manage', 'contact_messages.manage', 'support_tickets.manage',
            ],
        ];

        DB::transaction(function () use ($email, $password, $permissionCodes, $rolePermissions): void {
            foreach ([
                'super_admin' => 'Super Administrator',
                'manager' => 'Manager',
                'operator' => 'Operations staff',
            ] as $code => $name) {
                DB::table('roles')->insertOrIgnore([
                    'code' => $code,
                    'name' => $name,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('roles')->where('code', $code)->update(['name' => $name, 'is_system' => true, 'updated_at' => now()]);
            }
            $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');

            foreach ($permissionCodes as $code) {
                DB::table('permissions')->insertOrIgnore([
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['super_admin' => $permissionCodes] + $rolePermissions as $code => $codes) {
                $assignedRoleId = DB::table('roles')->where('code', $code)->value('id');
                $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
                DB::table('role_permission')->where('role_id', $assignedRoleId)->delete();
                DB::table('role_permission')->insert($permissionIds->map(fn (int $permissionId): array => [
                    'role_id' => $assignedRoleId,
                    'permission_id' => $permissionId,
                ])->all());
            }

            $admin = User::query()->where('email', $email)->first();
            if ($admin) {
                $alreadyAdmin = DB::table('user_role')
                    ->where('user_id', $admin->id)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $alreadyAdmin) {
                    throw new RuntimeException('HS_SHOP_ADMIN_EMAIL already belongs to a non-administrator account. Use another email or grant access explicitly.');
                }
            } else {
                $admin = User::query()->create([
                    'name' => trim((string) env('HS_SHOP_ADMIN_NAME', 'HS Shop Admin')),
                    'email' => $email,
                    'password' => Hash::make($password),
                    'preferred_locale' => 'ar',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $admin->is_active) {
                throw new RuntimeException('The configured HS_SHOP_ADMIN_EMAIL belongs to an inactive user.');
            }

            DB::table('user_role')->insertOrIgnore([
                'user_id' => $admin->id,
                'role_id' => $roleId,
                'assigned_by' => null,
                'created_at' => now(),
            ]);
        });
    }
}
