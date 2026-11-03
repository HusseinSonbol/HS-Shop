<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteOrderPlacementService
{
    public function __construct(
        private readonly CartManagementService $carts,
        private readonly CartDisplayService $cartDisplay,
        private readonly CheckoutQuoteService $quotes,
        private readonly ShippingRateService $shippingRates,
    ) {}

    /** @param array<string, mixed> $data
     *  @return array{order_id:int,public_id:string,order_number:string}
     */
    public function place(User $user, array $data, string $locale): array
    {
        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $existing = $this->existingOrder($data['idempotency_key'], $customer->id);
        if ($existing) {
            return $existing;
        }

        $cart = $this->carts->currentCart(request());
        if (! $cart) {
            $existing = $this->existingOrder($data['idempotency_key'], $customer->id);
            if ($existing) {
                return $existing;
            }
            throw ValidationException::withMessages(['cart' => __('store.checkout.validation.cart_empty')]);
        }

        try {
            return DB::transaction(function () use ($user, $customer, $cart, $data, $locale): array {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $lockedUser->is_active || $lockedUser->trashed()) {
                    throw ValidationException::withMessages(['account' => __('store.checkout.validation.account_inactive')]);
                }

                $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $lockedCart = Cart::query()
                    ->whereKey($cart->id)
                    ->where('customer_id', $lockedCustomer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = $this->existingOrder($data['idempotency_key'], $lockedCustomer->id, lock: true);
                if ($existing) {
                    return $existing;
                }
                if ($lockedCart->status !== 'active') {
                    throw ValidationException::withMessages(['cart' => __('store.checkout.validation.cart_empty')]);
                }

                $cartItems = CartItem::query()->where('cart_id', $lockedCart->id)->orderBy('id')->lockForUpdate()->get();
                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => __('store.checkout.validation.cart_empty')]);
                }

                $variants = ProductVariant::query()
                    ->whereIn('id', $cartItems->pluck('variant_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                DB::table('products')
                    ->whereIn('id', $variants->pluck('product_id')->unique())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $rates = $this->shippingRates->all(lock: true);
                $rate = collect($rates)->firstWhere('code', $data['shipping_rate_code']);
                if (! $rate) {
                    throw ValidationException::withMessages(['shipping_rate_code' => __('store.checkout.validation.governorate_invalid')]);
                }

                $address = $this->resolveAddress($lockedCustomer, $data, $rate);
                $matchedRate = $this->shippingRates->matchAddress($address, $rates);
                if (! $matchedRate || ! hash_equals($matchedRate['code'], $rate['code'])) {
                    throw ValidationException::withMessages(['shipping_rate_code' => __('store.checkout.validation.governorate_invalid')]);
                }

                $cartView = $this->cartDisplay->build($lockedCart, $locale);
                if ($cartView['has_unavailable_items']) {
                    throw ValidationException::withMessages(['cart' => __('store.checkout.validation.unavailable_items')]);
                }
                foreach ($cartView['items'] as $item) {
                    if (! $item->storefront_is_available || $item->storefront_regular_price === null || $item->storefront_raw_line_total === null) {
                        throw ValidationException::withMessages(['cart' => __('store.checkout.validation.unavailable_items')]);
                    }
                    if ($this->hasFractionalQuantity((string) $item->quantity)) {
                        throw ValidationException::withMessages(['cart' => __('store.checkout.validation.invalid_quantity')]);
                    }
                    if (($item->variant?->currency ?? $lockedCart->currency) !== 'EGP') {
                        throw ValidationException::withMessages(['cart' => __('store.checkout.validation.currency_invalid')]);
                    }
                }

                $expectedToken = $this->quotes->token(
                    $lockedCart,
                    $lockedCustomer->id,
                    $cartView['items'],
                    $rate,
                    $cartView['raw_subtotal'],
                    $cartView['raw_discount_total'],
                );
                if (! hash_equals($expectedToken, $data['quote_token'])) {
                    throw ValidationException::withMessages(['quote_token' => __('store.checkout.validation.price_changed')]);
                }

                $shippingCharge = (string) $rate['charge'];
                $grandTotal = $this->quotes->addAmounts($cartView['raw_subtotal'], $shippingCharge);
                $now = now();
                $orderNumber = 'HS-'.(string) Str::ulid();
                $publicId = (string) Str::uuid();

                $orderId = (int) DB::table('orders')->insertGetId([
                    'order_number' => $orderNumber,
                    'public_id' => $publicId,
                    'customer_id' => $lockedCustomer->id,
                    'channel' => 'website',
                    'created_by_user_id' => $user->id,
                    'status' => 'new',
                    'currency' => 'EGP',
                    'subtotal' => $cartView['raw_subtotal'],
                    'discount_total' => $cartView['raw_discount_total'],
                    'customer_shipping_charge' => $shippingCharge,
                    'tax_total' => null,
                    'grand_total' => $grandTotal,
                    'applied_revision_id' => null,
                    'customer_note' => $this->nullableTrim($data['customer_note'] ?? null),
                    'placed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $revisionId = (int) DB::table('order_revisions')->insertGetId([
                    'order_id' => $orderId,
                    'revision_no' => 1,
                    'revision_type' => 'initial',
                    'status' => 'applied',
                    'change_summary' => null,
                    'currency' => 'EGP',
                    'subtotal' => $cartView['raw_subtotal'],
                    'discount_total' => $cartView['raw_discount_total'],
                    'customer_shipping_charge' => $shippingCharge,
                    'tax_total' => null,
                    'grand_total' => $grandTotal,
                    'proposed_by' => $user->id,
                    'proposed_at' => $now,
                    'expires_at' => null,
                    'decided_at' => null,
                    'applied_at' => $now,
                    'idempotency_key' => $data['idempotency_key'],
                    'created_at' => $now,
                ]);

                $this->snapshotLines($orderId, $revisionId, $cartView['items'], $locale, $now);
                DB::table('order_revision_addresses')->insert([
                    'order_revision_id' => $revisionId,
                    'address_type' => 'shipping',
                    'recipient_name' => $address['recipient_name'],
                    'phone' => $address['phone'],
                    'country_code' => 'EG',
                    'governorate' => $address['governorate'],
                    'city' => $address['city'],
                    'district' => $address['district'] ?? null,
                    'street' => $address['street'],
                    'building' => $address['building'] ?? null,
                    'floor' => $address['floor'] ?? null,
                    'apartment' => $address['apartment'] ?? null,
                    'landmark' => $address['landmark'] ?? null,
                    'postal_code' => $address['postal_code'] ?? null,
                    'created_at' => $now,
                ]);

                DB::table('orders')->where('id', $orderId)->update([
                    'applied_revision_id' => $revisionId,
                    'updated_at' => $now,
                ]);
                DB::table('payments')->insert([
                    'order_id' => $orderId,
                    'payment_number' => 'PAY-'.(string) Str::ulid(),
                    'method' => 'cod',
                    'status' => 'pending',
                    'amount' => $grandTotal,
                    'currency' => 'EGP',
                    'provider' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('order_activity_events')->insert([
                    'order_id' => $orderId,
                    'actor_user_id' => $user->id,
                    'event_type' => 'created',
                    'previous_status' => null,
                    'current_status' => 'new',
                    'close_outcome' => null,
                    'reason_code' => null,
                    'details' => json_encode(['channel' => 'website'], JSON_THROW_ON_ERROR),
                    'next_follow_up_at' => null,
                    'occurred_at' => $now,
                    'idempotency_key' => 'checkout:'.$data['idempotency_key'],
                    'created_at' => $now,
                ]);

                $lockedCart->status = 'converted';
                $lockedCart->guest_token = null;
                $lockedCart->save();

                return ['order_id' => $orderId, 'public_id' => $publicId, 'order_number' => $orderNumber];
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->existingOrder($data['idempotency_key'], $customer->id)
                ?? throw $exception;
        }
    }

    /** @return array{order_id:int,public_id:string,order_number:string}|null */
    private function existingOrder(string $idempotencyKey, int $customerId, bool $lock = false): ?array
    {
        $query = DB::table('order_revisions as revisions')
            ->join('orders', 'orders.id', '=', 'revisions.order_id')
            ->where('revisions.idempotency_key', $idempotencyKey)
            ->where('orders.customer_id', $customerId)
            ->select(['orders.id as order_id', 'orders.public_id', 'orders.order_number']);
        if ($lock) {
            $query->lockForUpdate();
        }
        $order = $query->first();

        return $order ? [
            'order_id' => (int) $order->order_id,
            'public_id' => (string) $order->public_id,
            'order_number' => (string) $order->order_number,
        ] : null;
    }

    /** @param array<string, mixed> $data
     *  @param array{code:string,governorate_ar:string,governorate_en:string,charge:string} $rate
     *  @return array<string, mixed>
     */
    private function resolveAddress(Customer $customer, array $data, array $rate): array
    {
        if ($data['address_source'] === 'saved') {
            $savedAddress = $customer->addresses()->whereKey($data['address_id'])->lockForUpdate()->firstOrFail();
            return $savedAddress->only([
                'recipient_name', 'phone', 'country_code', 'governorate', 'city', 'district', 'street',
                'building', 'floor', 'apartment', 'landmark', 'postal_code',
            ]);
        }

        $input = $data['shipping_address'];
        $address = [
            'recipient_name' => trim($input['recipient_name']),
            'phone' => trim($input['phone']),
            'country_code' => 'EG',
            'governorate' => app()->getLocale() === 'ar' ? $rate['governorate_ar'] : $rate['governorate_en'],
            'city' => trim($input['city']),
            'district' => $this->nullableTrim($input['district'] ?? null),
            'street' => trim($input['street']),
            'building' => $this->nullableTrim($input['building'] ?? null),
            'floor' => $this->nullableTrim($input['floor'] ?? null),
            'apartment' => $this->nullableTrim($input['apartment'] ?? null),
            'landmark' => $this->nullableTrim($input['landmark'] ?? null),
            'postal_code' => $this->nullableTrim($input['postal_code'] ?? null),
        ];

        if (filter_var($data['save_address'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $customer->addresses()->create([
                'label' => $this->nullableTrim($input['label'] ?? null),
                ...$address,
                'is_default' => false,
            ]);
        }

        return $address;
    }

    /** @param \Illuminate\Support\Collection<int, CartItem> $items */
    private function snapshotLines(int $orderId, int $revisionId, $items, string $locale, mixed $now): void
    {
        foreach ($items->values() as $index => $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            if (! $variant || ! $product) {
                throw ValidationException::withMessages(['cart' => __('store.checkout.validation.unavailable_items')]);
            }

            $orderItemId = (int) DB::table('order_items')->insertGetId([
                'order_id' => $orderId,
                'line_key' => (string) Str::uuid(),
                'created_at' => $now,
            ]);
            $translation = $product->translations->firstWhere('locale', $locale)
                ?? $product->translations->firstWhere('locale', 'en')
                ?? $product->translations->first();
            $image = $variant->images->firstWhere('is_primary', true)
                ?? $variant->images->first()
                ?? $product->images->firstWhere('is_primary', true)
                ?? $product->images->first();
            $variantSnapshot = [
                'sku' => $variant->sku,
                'name' => $item->storefront_variant_label,
                'variant_name' => $variant->variant_name,
                'attributes' => $variant->attributes->map(function ($attribute) use ($locale): array {
                    $value = $attribute->values->firstWhere('id', $attribute->pivot->attribute_value_id);
                    $attributeTranslation = $attribute->translations->firstWhere('locale', $locale)
                        ?? $attribute->translations->firstWhere('locale', 'en')
                        ?? $attribute->translations->first();
                    $valueTranslation = $value?->translations->firstWhere('locale', $locale)
                        ?? $value?->translations->firstWhere('locale', 'en')
                        ?? $value?->translations->first();

                    return [
                        'code' => $attribute->code,
                        'name' => $attributeTranslation?->name ?? $attribute->code,
                        'value_code' => $value?->code,
                        'value' => $valueTranslation?->value ?? $value?->code,
                    ];
                })->values()->all(),
            ];

            DB::table('order_revision_items')->insert([
                'order_revision_id' => $revisionId,
                'order_item_id' => $orderItemId,
                'line_no' => $index + 1,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku_snapshot' => $product->sku,
                'product_name_snapshot' => mb_substr((string) ($translation?->name ?? $product->sku), 0, 220),
                'variant_snapshot' => json_encode($variantSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'product_image_path_snapshot' => $image?->path,
                'quantity' => (string) $item->quantity,
                'regular_unit_price_snapshot' => $item->storefront_regular_price,
                'sale_unit_price_snapshot' => $item->storefront_sale_price,
                'charged_unit_price' => $item->storefront_charged_price,
                'discount_amount' => $item->storefront_raw_discount_amount,
                'tax_amount' => null,
                'line_total' => $item->storefront_raw_line_total,
                'currency' => 'EGP',
                'tax_snapshot' => null,
                'created_at' => $now,
            ]);
        }
    }

    private function hasFractionalQuantity(string $quantity): bool
    {
        [, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return trim($fraction, '0') !== '';
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
