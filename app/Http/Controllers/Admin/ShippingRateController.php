<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingRatesRequest;
use App\Services\Storefront\ShippingRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShippingRateController extends Controller
{
    public function edit(ShippingRateService $shippingRates): View
    {
        return view('admin.settings.shipping-rates', ['rates' => $shippingRates->all()]);
    }

    public function update(ShippingRatesRequest $request, ShippingRateService $shippingRates): RedirectResponse
    {
        $shippingRates->save($request->validated('rates', []), (int) $request->user()->id);

        return redirect()->route('admin.settings.shipping-rates.edit')->with('status', __('admin.shipping_rates.saved'));
    }
}
