<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingCompanyRequest;
use App\Services\Admin\ShippingCompanyManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShippingCompanyController extends Controller
{
    public function index(): View
    {
        return view('admin.shipping-companies.index', [
            'companies' => DB::table('shipping_companies')->orderByDesc('is_active')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(ShippingCompanyRequest $request, ShippingCompanyManagementService $companies): RedirectResponse
    {
        $companies->create($request->validated('name'), $request->user());

        return redirect()->route('admin.shipping-companies.index')->with('status', __('admin.shipping_companies.created'));
    }

    public function update(ShippingCompanyRequest $request, int $company, ShippingCompanyManagementService $companies): RedirectResponse
    {
        $companies->update($company, $request->validated('name'), $request->user());

        return redirect()->route('admin.shipping-companies.index')->with('status', __('admin.shipping_companies.updated'));
    }

    public function toggle(int $company, ShippingCompanyManagementService $companies, Request $request): RedirectResponse
    {
        $active = $companies->toggleActive($company, $request->user());

        return redirect()->route('admin.shipping-companies.index')->with('status', __($active ? 'admin.shipping_companies.activated' : 'admin.shipping_companies.deactivated'));
    }
}
