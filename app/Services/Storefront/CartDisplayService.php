<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CartDisplayService
{
    /** @return array{items: Collection<int, CartItem>, subtotal: string, raw_subtotal:string, raw_discount_total:string, has_unavailable_items: bool} */
    public function build(?Cart $cart, string $locale): array
    {
        if (! $cart) {
            return ['items' => collect(), 'subtotal' => '0', 'raw_subtotal' => '0.0000', 'raw_discount_total' => '0.0000', 'has_unavailable_items' => false];
        }

        $items = $cart->items()
            ->with([
                'variant.attributes.translations',
                'variant.attributes.values.translations',
                'variant.images',
                'variant.product.translations',
                'variant.product.images',
            ])
            ->orderBy('added_at')
            ->orderBy('id')
            ->get();

        $prices = $this->currentAmounts($cart, $items->pluck('id')->all());
        $hasUnavailableItems = false;

        $items->each(function (CartItem $item) use ($locale, $prices, &$hasUnavailableItems): void {
            $variant = $item->variant;
            $product = $variant?->product;
            $isAvailable = $variant
                && $product
                && $variant->is_active
                && $product->status === 'published'
                && $product->store_availability_status === 'available'
                && $product->published_at !== null
                && $product->published_at->lte(now());

            if (! $isAvailable) {
                $hasUnavailableItems = true;
            }

            $productTranslation = $product
                ? $this->translation($product->translations, $locale)
                : null;
            $image = $variant?->images->firstWhere('is_primary', true)
                ?? $variant?->images->first()
                ?? $product?->images->firstWhere('is_primary', true)
                ?? $product?->images->first();
            $regularPrice = $variant?->current_regular_price;
            $salePrice = $variant?->current_sale_price;
            $hasDiscount = $salePrice !== null && $regularPrice !== null && $this->compareDecimals($salePrice, $regularPrice) < 0;

            $item->setAttribute('storefront_product_name', $productTranslation?->name ?? $product?->sku ?? __('store.cart.removed_product'));
            $item->setAttribute('storefront_variant_label', $this->variantLabel($variant, $locale));
            $item->setAttribute('storefront_image_url', $image ? Storage::disk('public')->url($image->path) : null);
            $item->setAttribute('storefront_is_available', (bool) $isAvailable);
            $item->setAttribute('storefront_unit_price', $this->formatMoney($hasDiscount ? $salePrice : $regularPrice));
            $item->setAttribute('storefront_line_total', isset($prices['lines'][$item->id]) ? $this->formatMoney($prices['lines'][$item->id]) : null);
            $item->setAttribute('storefront_raw_line_total', $prices['lines'][$item->id] ?? null);
            $item->setAttribute('storefront_raw_discount_amount', $prices['discounts'][$item->id] ?? '0.0000');
            $item->setAttribute('storefront_regular_price', $regularPrice === null ? null : (string) $regularPrice);
            $item->setAttribute('storefront_sale_price', $salePrice === null ? null : (string) $salePrice);
            $item->setAttribute('storefront_charged_price', $hasDiscount ? (string) $salePrice : ($regularPrice === null ? null : (string) $regularPrice));
            $item->setAttribute('storefront_product_id', $product?->id);
            $item->setAttribute('storefront_product_sku', $product?->sku);
            $item->setAttribute('storefront_variant_sku', $variant?->sku);
            $item->setAttribute('storefront_image_path', $image?->path);
        });

        return [
            'items' => $items,
            'subtotal' => $this->formatMoney($prices['subtotal'] ?? '0') ?? '0',
            'raw_subtotal' => (string) ($prices['subtotal'] ?? '0.0000'),
            'raw_discount_total' => (string) ($prices['discount_total'] ?? '0.0000'),
            'has_unavailable_items' => $hasUnavailableItems,
        ];
    }

    /** @param list<int> $itemIds
     *  @return array{subtotal: string|null, discount_total: string|null, lines: array<int, string>, discounts: array<int, string>}
     */
    private function currentAmounts(Cart $cart, array $itemIds): array
    {
        if ($itemIds === []) {
            return ['subtotal' => '0.0000', 'discount_total' => '0.0000', 'lines' => [], 'discounts' => []];
        }

        $priceExpression = 'CASE WHEN pv.current_sale_price IS NOT NULL AND pv.current_sale_price < pv.current_regular_price THEN pv.current_sale_price ELSE pv.current_regular_price END';
        $discountExpression = "ci.quantity * (pv.current_regular_price - ($priceExpression))";
        $baseQuery = DB::table('cart_items as ci')
            ->join('product_variants as pv', 'pv.id', '=', 'ci.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('ci.cart_id', $cart->id)
            ->whereIn('ci.id', $itemIds)
            ->where('pv.is_active', true)
            ->whereNull('pv.deleted_at')
            ->where('p.status', 'published')
            ->where('p.store_availability_status', 'available')
            ->whereNotNull('p.published_at')
            ->where('p.published_at', '<=', now())
            ->whereNull('p.deleted_at');

        $lineRows = (clone $baseQuery)
            ->select('ci.id')
            ->selectRaw("ci.quantity * ($priceExpression) AS line_total")
            ->selectRaw("$discountExpression AS discount_amount")
            ->get();
        $subtotal = (clone $baseQuery)->selectRaw("SUM(ci.quantity * ($priceExpression)) AS subtotal")->value('subtotal');
        $discountTotal = (clone $baseQuery)->selectRaw("SUM($discountExpression) AS discount_total")->value('discount_total');

        return [
            'subtotal' => $subtotal === null ? '0.0000' : (string) $subtotal,
            'discount_total' => $discountTotal === null ? '0.0000' : (string) $discountTotal,
            'lines' => $lineRows->mapWithKeys(fn ($row) => [(int) $row->id => (string) $row->line_total])->all(),
            'discounts' => $lineRows->mapWithKeys(fn ($row) => [(int) $row->id => (string) $row->discount_amount])->all(),
        ];
    }

    private function variantLabel(?ProductVariant $variant, string $locale): string
    {
        if (! $variant) {
            return __('store.cart.variant_unavailable');
        }

        if (filled($variant->variant_name)) {
            return $variant->variant_name;
        }

        $labels = $variant->attributes->map(function ($attribute) use ($locale): ?string {
            $value = $attribute->values->firstWhere('id', $attribute->pivot->attribute_value_id);
            if (! $value) {
                return null;
            }

            $attributeTranslation = $this->translation($attribute->translations, $locale);
            $valueTranslation = $this->translation($value->translations, $locale);
            $attributeName = $attributeTranslation?->name ?? $attribute->code;
            $valueName = $valueTranslation?->value ?? $value->code;

            return $attributeName.': '.$valueName;
        })->filter()->values();

        return $labels->isNotEmpty() ? $labels->join(' · ') : $variant->sku;
    }

    private function translation(Collection $translations, string $locale): mixed
    {
        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', 'en')
            ?? $translations->first();
    }

    private function formatMoney(?string $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($whole, '0') ?: '0');
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private function compareDecimals(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = array_pad(explode('.', $left, 2), 2, '');
        [$rightWhole, $rightFraction] = array_pad(explode('.', $right, 2), 2, '');
        $leftWhole = ltrim($leftWhole, '0') ?: '0';
        $rightWhole = ltrim($rightWhole, '0') ?: '0';

        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }

        $wholeComparison = strcmp($leftWhole, $rightWhole);
        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }

        $scale = max(strlen($leftFraction), strlen($rightFraction));

        return strcmp(str_pad($leftFraction, $scale, '0'), str_pad($rightFraction, $scale, '0')) <=> 0;
    }
}
