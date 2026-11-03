<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartManagementService
{
    public function itemCount(Request $request): string
    {
        $cart = $this->currentCart($request);
        if (! $cart) {
            return '0';
        }

        $count = (string) ($cart->items()->sum('quantity') ?? '0');
        if (! str_contains($count, '.')) {
            return $count;
        }

        return rtrim(rtrim($count, '0'), '.') ?: '0';
    }

    public function currentCart(Request $request, bool $create = false): ?Cart
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            abort_unless($customer, 403);

            $cart = $this->activeCustomerCart($customer);
            if ($cart || ! $create) {
                return $cart;
            }

            return DB::transaction(function () use ($customer): Cart {
                $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                return $this->activeCustomerCart($lockedCustomer) ?? Cart::query()->create([
                    'customer_id' => $lockedCustomer->id,
                    'status' => 'active',
                    'currency' => 'EGP',
                ]);
            }, attempts: 3);
        }

        $guestToken = $request->session()->get('hs_shop_guest_cart_token');
        if (! is_string($guestToken) || ! Str::isUuid($guestToken)) {
            if (! $create) {
                return null;
            }

            $guestToken = $this->guestToken();
            $request->session()->put('hs_shop_guest_cart_token', $guestToken);
        }

        $cart = $this->activeGuestCart($guestToken);
        if ($cart || ! $create) {
            return $cart;
        }

        if (Cart::query()->where('guest_token', $guestToken)->exists()) {
            $guestToken = $this->guestToken();
            $request->session()->put('hs_shop_guest_cart_token', $guestToken);
        }

        try {
            return Cart::query()->create([
                'guest_token' => $guestToken,
                'status' => 'active',
                'currency' => 'EGP',
            ]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->activeGuestCart($guestToken) ?? throw $exception;
        }
    }

    public function addItem(Request $request, ProductVariant $variant, string $quantity): CartItem
    {
        $cart = $this->currentCart($request, create: true);

        return DB::transaction(function () use ($cart, $variant, $quantity): CartItem {
            $cart = Cart::query()->whereKey($cart->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->quantity = $this->addQuantities((string) $item->quantity, $quantity);
                $this->assertQuantityFits($item->quantity);
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

    public function updateItem(Cart $cart, int $itemId, string $quantity): void
    {
        DB::transaction(function () use ($cart, $itemId, $quantity): void {
            $lockedCart = Cart::query()->whereKey($cart->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $item = CartItem::query()
                ->where('cart_id', $lockedCart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();
            $item->quantity = $quantity;
            $item->save();
        }, attempts: 3);
    }

    public function removeItem(Cart $cart, int $itemId): void
    {
        DB::transaction(function () use ($cart, $itemId): void {
            $lockedCart = Cart::query()->whereKey($cart->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            CartItem::query()
                ->where('cart_id', $lockedCart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        }, attempts: 3);
    }

    public function mergeGuestCartIntoCustomer(Request $request, Customer $customer): bool
    {
        $guestToken = $request->session()->get('hs_shop_guest_cart_token');
        if (! is_string($guestToken) || ! Str::isUuid($guestToken)) {
            return false;
        }

        $merged = DB::transaction(function () use ($guestToken, $customer): bool {
            $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $guestCart = Cart::query()
                ->where('guest_token', $guestToken)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $guestCart) {
                return false;
            }

            $customerCart = $this->activeCustomerCart($lockedCustomer, lockForUpdate: true);
            if (! $customerCart) {
                $guestCart->customer_id = $lockedCustomer->id;
                $guestCart->guest_token = null;
                $guestCart->save();

                return true;
            }

            $guestItems = CartItem::query()->where('cart_id', $guestCart->id)->orderBy('id')->lockForUpdate()->get();
            foreach ($guestItems as $guestItem) {
                $customerItem = CartItem::query()
                    ->where('cart_id', $customerCart->id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($customerItem) {
                    $customerItem->quantity = $this->addQuantities((string) $customerItem->quantity, (string) $guestItem->quantity);
                    $this->assertQuantityFits($customerItem->quantity);
                    $customerItem->save();
                    $guestItem->delete();
                } else {
                    $guestItem->cart_id = $customerCart->id;
                    $guestItem->save();
                }
            }

            $guestCart->status = 'merged';
            $guestCart->guest_token = null;
            $guestCart->save();

            return true;
        }, attempts: 3);

        if ($merged) {
            $request->session()->forget('hs_shop_guest_cart_token');
        }

        return $merged;
    }

    public function guestToken(): string
    {
        return (string) Str::uuid();
    }

    private function activeCustomerCart(Customer $customer, bool $lockForUpdate = false): ?Cart
    {
        $query = Cart::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function activeGuestCart(string $guestToken): ?Cart
    {
        return Cart::query()
            ->where('guest_token', $guestToken)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
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

    private function assertQuantityFits(string $quantity): void
    {
        if (strlen(strstr($quantity, '.', true) ?: $quantity) > 15) {
            throw ValidationException::withMessages([
                'quantity' => __('store.cart.validation.quantity_max'),
            ]);
        }
    }
}
