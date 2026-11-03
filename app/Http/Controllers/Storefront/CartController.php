<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\Storefront\CartDisplayService;
use App\Services\Storefront\CartManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartManagementService $carts, CartDisplayService $display): View
    {
        $cart = $carts->currentCart($request);
        $cartView = $display->build($cart, app()->getLocale());

        return view('storefront.cart.index', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'cart' => $cart,
            ...$cartView,
        ]);
    }

    public function update(
        UpdateCartItemRequest $request,
        CartItem $cartItem,
        CartManagementService $carts,
    ): RedirectResponse {
        $cart = $carts->currentCart($request);
        abort_unless($cart, 404);

        $carts->updateItem($cart, $cartItem->id, (string) $request->validated('quantity'));

        return redirect()->route('store.cart.index')->with('cart_status', __('store.cart.updated'));
    }

    public function destroy(Request $request, CartItem $cartItem, CartManagementService $carts): RedirectResponse
    {
        $cart = $carts->currentCart($request);
        abort_unless($cart, 404);

        $carts->removeItem($cart, $cartItem->id);

        return redirect()->route('store.cart.index')->with('cart_status', __('store.cart.removed'));
    }
}
