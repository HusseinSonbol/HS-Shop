<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use App\Services\Admin\SupplierManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'scope' => ['nullable', 'in:active,archived']]);
        $suppliers = Supplier::query()
            ->when(($filters['scope'] ?? 'active') === 'archived', fn ($query) => $query->onlyTrashed())
            ->when(! empty($filters['q']), fn ($query) => $query->where(function ($search) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $search->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('phone', 'like', $term);
            }))
            ->withCount('productSuppliers')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.suppliers.index', ['suppliers' => $suppliers, 'filters' => $filters + ['scope' => 'active']]);
    }

    public function create(): View
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(SupplierRequest $request, SupplierManagementService $suppliers): RedirectResponse
    {
        $supplier = $suppliers->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', __('admin.suppliers.created'));
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['productSuppliers.product.translations'])->loadCount('productSuppliers');

        return view('admin.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierManagementService $suppliers): RedirectResponse
    {
        $suppliers->update($supplier, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', __('admin.suppliers.updated'));
    }

    public function archive(Supplier $supplier, SupplierManagementService $suppliers): RedirectResponse
    {
        $suppliers->archive($supplier, (int) request()->user()->id);

        return redirect()->route('admin.suppliers.index')->with('status', __('admin.suppliers.archived'));
    }

    public function restore(Supplier $supplier, SupplierManagementService $suppliers): RedirectResponse
    {
        abort_unless($supplier->trashed(), 404);
        $suppliers->restore($supplier, (int) request()->user()->id);

        return redirect()->route('admin.suppliers.index', ['scope' => 'archived'])->with('status', __('admin.suppliers.restored'));
    }
}
