<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SetProductFavoriteRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Storefront\FavoriteManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProductFavoriteController extends Controller
{
    public function update(
        SetProductFavoriteRequest $request,
        Product $product,
        FavoriteManagementService $favorites,
    ): RedirectResponse {
        abort_unless(
            $product->status === 'published'
                && $product->store_availability_status === 'available'
                && $product->published_at !== null
                && $product->published_at->lte(now()),
            404,
        );

        $customerUser = Auth::guard('customer')->user();
        $customer = $customerUser?->customer;

        if (! $customer) {
            $favorites->rememberPendingFavorite($request, $product);

            return redirect()->route('store.account.login')
                ->with('account_notice', __('store.favorites.login_required'));
        }

        $isFavorite = $request->boolean('is_favorite');
        $favorites->setFavorite($customer, $product, $isFavorite);

        return back()->with(
            'favorite_status',
            $isFavorite ? __('store.favorites.added') : __('store.favorites.removed'),
        );
    }
}
