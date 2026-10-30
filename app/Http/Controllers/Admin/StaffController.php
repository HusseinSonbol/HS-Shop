<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\User;
use App\Services\Admin\StaffManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = User::query()
            ->with('roles:id,code,name')
            ->whereHas('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('admin.staff.form', [
            'staffMember' => new User,
            'roles' => $this->availableRoles(),
            'assignedRole' => null,
        ]);
    }

    public function store(StaffRequest $request, StaffManagementService $staffManagement): RedirectResponse
    {
        $staffManagement->create($request->validated());

        return redirect()->route('admin.staff.index')->with('status', __('admin.staff.created'));
    }

    public function edit(User $user): View
    {
        abort_unless($user->roles()->exists(), 404);

        return view('admin.staff.form', [
            'staffMember' => $user,
            'roles' => $this->availableRoles(),
            'assignedRole' => $user->roles()->value('roles.code'),
        ]);
    }

    public function update(StaffRequest $request, User $user, StaffManagementService $staffManagement): RedirectResponse
    {
        abort_unless($user->roles()->exists(), 404);
        $staffManagement->update($user, $request->validated());

        return redirect()->route('admin.staff.index')->with('status', __('admin.staff.updated'));
    }

    public function toggleActive(User $user, StaffManagementService $staffManagement): RedirectResponse
    {
        abort_unless($user->roles()->exists(), 404);
        $active = ! $user->is_active;
        $staffManagement->setActive($user, $active);

        return back()->with('status', __($active ? 'admin.staff.activated' : 'admin.staff.deactivated'));
    }

    public function destroy(User $user, StaffManagementService $staffManagement): RedirectResponse
    {
        abort_unless($user->roles()->exists(), 404);
        $staffManagement->archive($user);

        return redirect()->route('admin.staff.index')->with('status', __('admin.staff.archived'));
    }

    /** @return Collection<int, object> */
    private function availableRoles()
    {
        return DB::table('roles')->whereIn('code', ['super_admin', 'manager', 'operator'])
            ->orderByRaw("CASE code WHEN 'super_admin' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->get(['code', 'name']);
    }
}
