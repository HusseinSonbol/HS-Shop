<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddCartItemRequest;
use App\Models\ProductVariant;
use App\Services\Storefront\CartManagementService;
use Illuminate\Http\RedirectResponse;

class CartItemController extends Controller
{
    public function store(AddCartItemRequest $request, CartManagementService $cartManagement): RedirectResponse
    {
        $variant = ProductVariant::query()
            ->with('product')
            ->whereKey($request->integer('variant_id'))
            ->where('is_active', true)
            ->firstOrFail();
        $product = $variant->product;

        abort_unless(
            $product
                && $product->status === 'published'
                && $product->store_availability_status === 'available'
                && $product->published_at !== null
                && $product->published_at->lte(now()),
            404,
        );

        $guestToken = $request->session()->get('hs_shop_guest_cart_token');
        if (! is_string($guestToken) || ! \Illuminate\Support\Str::isUuid($guestToken)) {
            $guestToken = $cartManagement->guestToken();
            $request->session()->put('hs_shop_guest_cart_token', $guestToken);
        }

        $cartManagement->addGuestItem($guestToken, $variant, (string) $request->validated('quantity'));

        return back()->with('cart_status', __('store.cart.added'));
    }
}
