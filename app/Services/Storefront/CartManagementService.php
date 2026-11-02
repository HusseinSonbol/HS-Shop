<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartManagementService
{
    public function addGuestItem(string $guestToken, ProductVariant $variant, string $quantity): CartItem
    {
        return DB::transaction(function () use ($guestToken, $variant, $quantity): CartItem {
            $cart = Cart::query()
                ->where('guest_token', $guestToken)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                try {
                    $cart = Cart::query()->create([
                        'guest_token' => $guestToken,
                        'status' => 'active',
                        'currency' => 'EGP',
                    ]);
                } catch (QueryException $exception) {
                    if ($exception->getCode() !== '23000') {
                        throw $exception;
                    }

                    $cart = Cart::query()
                        ->where('guest_token', $guestToken)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->quantity = $this->addQuantities((string) $item->quantity, $quantity);
                if (strlen(strstr($item->quantity, '.', true) ?: $item->quantity) > 15) {
                    throw ValidationException::withMessages([
                        'quantity' => __('store.cart.validation.quantity_max'),
                    ]);
                }
                $item->save();

                return $item;
            }

            return $cart->items()->create([
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'added_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function guestToken(): string
    {
        return (string) Str::uuid();
    }

    private function addQuantities(string $first, string $second): string
    {
        [$firstWhole, $firstFraction] = array_pad(explode('.', $first, 2), 2, '');
        [$secondWhole, $secondFraction] = array_pad(explode('.', $second, 2), 2, '');
        $fraction = (int) str_pad($firstFraction, 3, '0') + (int) str_pad($secondFraction, 3, '0');
        $carry = intdiv($fraction, 1000);
        $fraction %= 1000;
        $whole = (int) $firstWhole + (int) $secondWhole + $carry;

        return $whole.'.'.str_pad((string) $fraction, 3, '0', STR_PAD_LEFT);
    }
}
