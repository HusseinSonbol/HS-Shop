<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductSupplierRequest;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Services\Admin\ProductSupplierManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductSupplierController extends Controller
{
    public function create(Supplier $supplier): View
    {
        $products = Product::query()->with('translations')->orderBy('id')->get();

        return view('admin.suppliers.offer-form', ['supplier' => $supplier, 'offer' => new ProductSupplier, 'products' => $products]);
    }

    public function store(ProductSupplierRequest $request, Supplier $supplier, ProductSupplierManagementService $offers): RedirectResponse
    {
        $offer = $offers->create($supplier, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.suppliers.offers.edit', [$supplier, $offer])->with('status', __('admin.suppliers.offer_created'));
    }

    public function edit(Supplier $supplier, ProductSupplier $offer): View
    {
        abort_unless((int) $offer->supplier_id === (int) $supplier->id, 404);
        $offer->load(['product.translations', 'history.changedBy']);

        return view('admin.suppliers.offer-form', ['supplier' => $supplier, 'offer' => $offer, 'products' => collect()]);
    }

    public function update(ProductSupplierRequest $request, Supplier $supplier, ProductSupplier $offer, ProductSupplierManagementService $offers): RedirectResponse
    {
        $offers->update($offer, $supplier, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.suppliers.offers.edit', [$supplier, $offer])->with('status', __('admin.suppliers.offer_updated'));
    }
}
