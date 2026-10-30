<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Admin\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function create(Customer $customer): View
    {
        abort_if($customer->trashed(), 404);

        return view('admin.customers.address-form', [
            'customer' => $customer,
            'address' => new CustomerAddress(['country_code' => 'EG']),
        ]);
    }

    public function store(CustomerAddressRequest $request, Customer $customer, CustomerAddressService $addresses): RedirectResponse
    {
        abort_if($customer->trashed(), 404);
        $addresses->create($customer, $request->validated());

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.address_created'));
    }

    public function edit(Customer $customer, CustomerAddress $address): View
    {
        abort_if($customer->trashed(), 404);
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);

        return view('admin.customers.address-form', compact('customer', 'address'));
    }

    public function update(CustomerAddressRequest $request, Customer $customer, CustomerAddress $address, CustomerAddressService $addresses): RedirectResponse
    {
        abort_if($customer->trashed(), 404);
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);
        $addresses->update($customer, $address, $request->validated());

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.address_updated'));
    }

    public function archive(Customer $customer, CustomerAddress $address, CustomerAddressService $addresses): RedirectResponse
    {
        abort_if($customer->trashed(), 404);
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);
        $addresses->archive($customer, $address);

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.address_archived'));
    }
}
