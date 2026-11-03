<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerAddressRequest;
use App\Http\Requests\Storefront\UpdateCustomerProfileRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Storefront\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user('customer');
        $customer = $this->activeCustomer($user);

        return view('storefront.account.index', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'user' => $user,
            'customer' => $customer,
            'addresses' => $customer->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
        ]);
    }

    public function updateProfile(UpdateCustomerProfileRequest $request, CustomerAccountService $accounts): RedirectResponse
    {
        $accounts->updateProfile($request->user('customer'), $request->validated());

        return redirect()->route('store.account.index')->with('account_status', __('store.customer_account.profile_updated'));
    }

    public function createAddress(Request $request): View
    {
        $this->activeCustomer($request->user('customer'));

        return view('storefront.account.address-form', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'address' => new CustomerAddress(['country_code' => 'EG']),
        ]);
    }

    public function storeAddress(CustomerAddressRequest $request, CustomerAccountService $accounts): RedirectResponse
    {
        $customer = $this->activeCustomer($request->user('customer'));
        $accounts->createAddress($customer, $request->validated());

        return redirect()->route('store.account.index')->with('account_status', __('store.customer_account.address_created'));
    }

    public function editAddress(Request $request, CustomerAddress $address): View
    {
        $customer = $this->activeCustomer($request->user('customer'));
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);

        return view('storefront.account.address-form', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'address' => $address,
        ]);
    }

    public function updateAddress(CustomerAddressRequest $request, CustomerAddress $address, CustomerAccountService $accounts): RedirectResponse
    {
        $customer = $this->activeCustomer($request->user('customer'));
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);
        $accounts->updateAddress($customer, $address, $request->validated());

        return redirect()->route('store.account.index')->with('account_status', __('store.customer_account.address_updated'));
    }

    public function archiveAddress(Request $request, CustomerAddress $address, CustomerAccountService $accounts): RedirectResponse
    {
        $customer = $this->activeCustomer($request->user('customer'));
        abort_unless($customer->addresses()->whereKey($address->id)->exists(), 404);
        $accounts->archiveAddress($customer, $address);

        return redirect()->route('store.account.index')->with('account_status', __('store.customer_account.address_archived'));
    }

    private function activeCustomer(?User $user): Customer
    {
        abort_unless($user, 403);

        return Customer::query()->where('user_id', $user->id)->firstOrFail();
    }
}
