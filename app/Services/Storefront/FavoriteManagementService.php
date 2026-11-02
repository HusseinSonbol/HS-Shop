<?php

namespace App\Services\Storefront;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteManagementService
{
    public function setFavorite(Customer $customer, Product $product, bool $isFavorite): void
    {
        if ($isFavorite) {
            DB::table('customer_favorites')->insertOrIgnore([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'created_at' => now(),
            ]);

            return;
        }

        DB::table('customer_favorites')
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->delete();
    }

    public function rememberPendingFavorite(Request $request, Product $product): void
    {
        $request->session()->put('pending_favorite_product_id', $product->id);
        $request->session()->put('url.intended', route('store.product', $product->slug));
    }

    public function applyPendingFavorite(Request $request, Customer $customer): bool
    {
        $productId = $request->session()->pull('pending_favorite_product_id');
        if (! $productId) {
            return false;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('status', 'published')
            ->where('store_availability_status', 'available')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if ($product) {
            $this->setFavorite($customer, $product, true);
        }

        return (bool) $product;
    }
}
