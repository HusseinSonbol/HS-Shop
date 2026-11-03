<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckoutQuoteService
{
    /** @param Collection<int, \App\Models\CartItem> $items
     *  @param array{code:string,charge:string} $rate
     */
    public function token(Cart $cart, int $customerId, Collection $items, array $rate, string $subtotal, string $discountTotal): string
    {
        $lines = $items->map(fn ($item): array => [
            'item_id' => (int) $item->id,
            'variant_id' => (int) $item->variant_id,
            'quantity' => (string) $item->quantity,
            'product_id' => $item->storefront_product_id,
            'product_sku' => $item->storefront_product_sku,
            'product_name' => $item->storefront_product_name,
            'variant_sku' => $item->storefront_variant_sku,
            'variant_name' => $item->storefront_variant_label,
            'regular_price' => $item->storefront_regular_price,
            'sale_price' => $item->storefront_sale_price,
            'charged_price' => $item->storefront_charged_price,
            'line_total' => $item->storefront_raw_line_total,
            'discount' => $item->storefront_raw_discount_amount,
            'available' => $item->storefront_is_available,
            'image_path' => $item->storefront_image_path,
        ])->values()->all();

        $payload = json_encode([
            'cart_id' => $cart->id,
            'customer_id' => $customerId,
            'currency' => $cart->currency,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_rate_code' => $rate['code'],
            'governorate_ar' => $rate['governorate_ar'],
            'governorate_en' => $rate['governorate_en'],
            'shipping_charge' => $rate['charge'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    public function addAmounts(string $first, string $second): string
    {
        $row = DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) + CAST(? AS DECIMAL(19,4)) AS amount', [$first, $second]);

        return (string) $row->amount;
    }

    public function formatAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($whole, '0') ?: '0');
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
