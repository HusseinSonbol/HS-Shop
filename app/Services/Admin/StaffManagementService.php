<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffManagementService
{
    /** @param array{name:string,email:string,phone?:?string,password?:?string,role:string,is_active:bool|string} $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create($this->userAttributes($data));
            $this->assignRole($user, $data['role']);
            $this->logActivity('staff.created', $user, null, [
                'role' => $data['role'],
                'is_active' => (bool) $user->is_active,
            ]);

            return $user;
        });
    }

    /** @param array{name:string,email:string,phone?:?string,password?:?string,role:string,is_active:bool|string} $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $currentRoles = $lockedUser->roles()->pluck('roles.code')->all();
            $before = ['role' => $currentRoles[0] ?? null, 'is_active' => (bool) $lockedUser->is_active];
            $nextRole = $data['role'];
            $nextIsActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);

            if (in_array('super_admin', $currentRoles, true) && ($nextRole !== 'super_admin' || ! $nextIsActive)) {
                $this->ensureAnotherActiveSuperAdminExists($lockedUser);
            }

            if (auth()->id() === $lockedUser->id && ($nextRole !== 'super_admin' || ! $nextIsActive)) {
                throw ValidationException::withMessages(['role' => __('admin.staff.errors.cannot_change_own_access')]);
            }

            $lockedUser->fill($this->userAttributes($data));
            $lockedUser->save();
            $this->assignRole($lockedUser, $nextRole);
            $this->logActivity('staff.updated', $lockedUser, $before, [
                'role' => $nextRole,
                'is_active' => $nextIsActive,
            ]);

            return $lockedUser->refresh();
        }, 3);
    }

    public function setActive(User $user, bool $active): void
    {
        DB::transaction(function () use ($user, $active): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $active && auth()->id() === $lockedUser->id) {
                throw ValidationException::withMessages(['staff' => __('admin.staff.errors.cannot_change_own_access')]);
            }

            if (! $active && $lockedUser->hasRoleCode('super_admin')) {
                $this->ensureAnotherActiveSuperAdminExists($lockedUser);
            }

            $before = ['role' => $lockedUser->roles()->value('roles.code'), 'is_active' => (bool) $lockedUser->is_active];
            $lockedUser->forceFill(['is_active' => $active])->save();
            $this->logActivity($active ? 'staff.activated' : 'staff.deactivated', $lockedUser, $before, [
                'role' => $before['role'],
                'is_active' => $active,
            ]);
        }, 3);
    }

    public function archive(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (auth()->id() === $lockedUser->id) {
                throw ValidationException::withMessages(['staff' => __('admin.staff.errors.cannot_archive_self')]);
            }

            if ($lockedUser->hasRoleCode('super_admin') && $lockedUser->is_active) {
                $this->ensureAnotherActiveSuperAdminExists($lockedUser);
            }

            $before = ['role' => $lockedUser->roles()->value('roles.code'), 'is_active' => (bool) $lockedUser->is_active];
            $lockedUser->delete();
            $this->logActivity('staff.archived', $lockedUser, $before, null);
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function userAttributes(array $data): array
    {
        $attributes = [
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
            'is_active' => filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN),
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        return $attributes;
    }

    private function assignRole(User $user, string $roleCode): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
        if (! $roleId) {
            throw ValidationException::withMessages(['role' => __('admin.staff.validation.role_invalid')]);
        }

        DB::table('user_role')->where('user_id', $user->id)->delete();
        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'assigned_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    private function logActivity(string $event, User $subject, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }

    private function ensureAnotherActiveSuperAdminExists(User $changingUser): void
    {
        $superAdminRole = DB::table('roles')->where('code', 'super_admin')->lockForUpdate()->first();
        $anotherActiveAdminExists = $superAdminRole && DB::table('user_role')
            ->join('users', 'users.id', '=', 'user_role.user_id')
            ->where('user_role.role_id', $superAdminRole->id)
            ->where('users.is_active', true)
            ->whereNull('users.deleted_at')
            ->where('users.id', '<>', $changingUser->id)
            ->exists();

        if (! $anotherActiveAdminExists) {
            throw ValidationException::withMessages(['staff' => __('admin.staff.errors.last_super_admin')]);
        }
    }
}
