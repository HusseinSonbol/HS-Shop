<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CartDisplayService;
use App\Services\Storefront\CartManagementService;
use App\Services\Storefront\CheckoutQuoteService;
use App\Services\Storefront\ShippingRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        CartManagementService $carts,
        CartDisplayService $display,
        CheckoutQuoteService $quotes,
        ShippingRateService $shippingRates,
    ): View
    {
        $cart = $carts->currentCart($request);
        $cartView = $display->build($cart, app()->getLocale());
        $user = Auth::guard('customer')->user();
        $customer = $user?->customer;
        $isCustomer = $customer !== null;
        $rates = $shippingRates->all();
        $addresses = $customer?->addresses()->orderByDesc('is_default')->orderByDesc('id')->get() ?? collect();
        $addressRateCodes = $addresses->mapWithKeys(fn ($address) => [
            $address->id => $shippingRates->matchAddress($address->toArray(), $rates)['code'] ?? null,
        ]);
        $defaultAddress = $addresses->firstWhere('is_default', true);
        $addressSource = old('address_source', $defaultAddress ? 'saved' : 'new');
        $selectedAddressId = old('address_id', $defaultAddress?->id);
        $selectedRateCode = old('shipping_rate_code');
        if ($addressSource === 'saved') {
            $selectedAddress = $addresses->firstWhere('id', (int) $selectedAddressId);
            $selectedRateCode = $selectedAddress
                ? ($shippingRates->matchAddress($selectedAddress->toArray(), $rates)['code'] ?? null)
                : null;
        }

        $rateQuotes = [];
        $idempotencyKey = null;
        if ($isCustomer && $cart && ! $cartView['items']->isEmpty()) {
            $idempotencyKey = $request->session()->get('checkout.idempotency_key');
            if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
                $idempotencyKey = (string) Str::uuid();
                $request->session()->put('checkout.idempotency_key', $idempotencyKey);
            }

            foreach ($rates as $rate) {
                $rateQuotes[$rate['code']] = [
                    'token' => $quotes->token($cart, $customer->id, $cartView['items'], $rate, $cartView['raw_subtotal'], $cartView['raw_discount_total']),
                    'charge' => $shippingRates->formatAmount($rate['charge']),
                    'grand_total' => $quotes->formatAmount($quotes->addAmounts($cartView['raw_subtotal'], $rate['charge'])),
                ];
            }
        }

        $selectedQuote = $selectedRateCode ? ($rateQuotes[$selectedRateCode] ?? null) : null;

        if (! $isCustomer) {
            $request->session()->put('url.intended', route('store.checkout'));
        }

        return view('storefront.checkout.index', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'isCustomer' => $isCustomer,
            'user' => $user,
            'customer' => $customer,
            'addresses' => $addresses,
            'addressRateCodes' => $addressRateCodes,
            'addressSource' => $addressSource,
            'selectedAddressId' => $selectedAddressId,
            'selectedRateCode' => $selectedRateCode,
            'rates' => $rates,
            'rateQuotes' => $rateQuotes,
            'selectedQuote' => $selectedQuote,
            'idempotencyKey' => $idempotencyKey,
            'cart' => $cart,
            ...$cartView,
        ]);
    }
}
