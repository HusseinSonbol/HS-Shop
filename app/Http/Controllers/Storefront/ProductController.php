<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function show(?string $slug = null): View|RedirectResponse
    {
        if (! filled($slug)) {
            return redirect()->route('store.shop');
        }

        $locale = app()->getLocale();
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('store_availability_status', 'available')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->with([
                'translations',
                'brand.translations',
                'categories.translations',
                'images',
                'variants' => fn ($query) => $query->where('is_active', true)
                    ->with(['attributes.translations', 'attributes.values.translations', 'images']),
            ])
            ->firstOrFail();

        abort_if($product->variants->isEmpty(), 404);

        $translation = $this->localized($product->translations, $locale);
        $product->setAttribute('display_name', $translation?->name ?? $product->sku);
        $product->setAttribute('display_short_description', $translation?->short_description);
        $product->setAttribute('display_description', $translation?->description);

        $product->variants->each(function (ProductVariant $variant) use ($locale): void {
            $attributes = [];
            foreach ($variant->attributes as $attribute) {
                $value = $attribute->values->firstWhere('id', $attribute->pivot->attribute_value_id);
                if (! $value) {
                    continue;
                }

                $attributes[$attribute->id] = [
                    'attribute_id' => $attribute->id,
                    'attribute_name' => $this->localized($attribute->translations, $locale)?->name ?? $attribute->code,
                    'value_id' => $value->id,
                    'value' => $this->localized($value->translations, $locale)?->value ?? $value->code,
                ];
            }

            $variant->setAttribute('display_attributes', $attributes);
            $variant->setAttribute(
                'display_label',
                $variant->variant_name ?: (collect($attributes)->pluck('value')->join(' · ') ?: $variant->sku),
            );
            $variant->setAttribute('display_regular_price', $this->formatMoney($variant->current_regular_price));
            $variant->setAttribute('display_sale_price', $this->formatMoney($variant->current_sale_price));
            $variant->setAttribute('display_image_urls', $variant->images->map(fn ($image) => Storage::disk('public')->url($image->path))->values());
        });

        $selectedVariant = $product->variants->firstWhere('id', (int) old('variant_id'))
            ?? $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();
        $product->setAttribute('display_variant', $selectedVariant);
        $product->setAttribute('display_images', $product->images
            ->whereNull('variant_id')
            ->map(fn ($image) => [
                'url' => Storage::disk('public')->url($image->path),
                'alt' => $image->alt_text ?: $product->display_name,
            ])->values());

        if ($product->display_images->isEmpty() && $selectedVariant) {
            $product->setAttribute('display_images', $selectedVariant->images->map(fn ($image) => [
                'url' => Storage::disk('public')->url($image->path),
                'alt' => $image->alt_text ?: $product->display_name,
            ])->values());
        }

        $category = $product->categories
            ->where('is_active', true)
            ->first(fn (Category $item) => (bool) $item->pivot->is_primary)
            ?? $product->categories->where('is_active', true)->first();
        $categoryName = $category
            ? ($this->localized($category->translations, $locale)?->name ?? $category->slug)
            : null;
        $brandName = $product->brand
            ? ($this->localized($product->brand->translations, $locale)?->name ?? $product->brand->slug)
            : null;

        $attributeGroups = $product->variants
            ->flatMap(fn (ProductVariant $variant) => $variant->display_attributes)
            ->groupBy('attribute_id')
            ->map(fn (Collection $items) => [
                'id' => $items->first()['attribute_id'],
                'name' => $items->first()['attribute_name'],
                'values' => $items->unique('value_id')->values(),
            ])->values();

        $reviews = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('moderation_status', 'approved')
            ->where('visibility_status', 'visible')
            ->latest('reviewed_at')
            ->limit(12)
            ->get(['id', 'rating', 'comment', 'reviewed_at']);

        $customer = Auth::guard('customer')->user()?->customer;
        $reviewableOrderItems = $customer
            ? DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('order_revision_items', function ($join): void {
                    $join->on('order_revision_items.order_item_id', '=', 'order_items.id')
                        ->on('order_revision_items.order_revision_id', '=', 'orders.applied_revision_id');
                })
                ->join('products', 'products.id', '=', 'order_revision_items.product_id')
                ->where('orders.customer_id', $customer->id)
                ->where('orders.status', 'received')
                ->where('orders.close_outcome', 'success')
                ->where('products.id', $product->id)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_reviews')->whereColumn('product_reviews.order_item_id', 'order_items.id'))
                ->orderByDesc('orders.placed_at')
                ->limit(20)
                ->get([
                    'order_items.id as order_item_id', 'orders.order_number',
                    'order_revision_items.product_name_snapshot', 'order_revision_items.variant_snapshot',
                ])
            : collect();
        $isFavorite = $customer?->favorites()->whereKey($product->id)->exists() ?? false;

        $relatedProducts = collect();
        if ($category) {
            $relatedProducts = Product::query()
                ->where('products.id', '<>', $product->id)
                ->where('status', 'published')
                ->where('store_availability_status', 'available')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('categories', fn (Builder $query) => $query->where('categories.id', $category->id)->where('categories.is_active', true))
                ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
                ->with(['translations', 'images', 'variants' => fn ($query) => $query->where('is_active', true)])
                ->latest('published_at')
                ->limit(4)
                ->get()
                ->map(function (Product $related) use ($locale): Product {
                    $relatedTranslation = $this->localized($related->translations, $locale);
                    $variant = $related->variants->firstWhere('is_default', true) ?? $related->variants->first();
                    $related->setAttribute('display_name', $relatedTranslation?->name ?? $related->sku);
                    $related->setAttribute('display_variant', $variant);
                    $image = $related->images->firstWhere('is_primary', true) ?? $related->images->first();
                    $related->setAttribute('display_image_url', $image ? Storage::disk('public')->url($image->path) : null);
                    if ($variant) {
                        $variant->setAttribute('display_regular_price', $this->formatMoney($variant->current_regular_price));
                        $variant->setAttribute('display_sale_price', $this->formatMoney($variant->current_sale_price));
                    }

                    return $related;
                });
        }

        $relatedFavoriteIds = $customer && $relatedProducts->isNotEmpty()
            ? $customer->favorites()->whereIn('products.id', $relatedProducts->pluck('id'))->pluck('products.id')->flip()
            : collect();
        $relatedProducts->each(fn (Product $related) => $related->setAttribute('is_favorite', $relatedFavoriteIds->has($related->id)));

        $variantOptions = $product->variants->map(fn (ProductVariant $variant) => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'label' => $variant->display_label,
            'attributes' => collect($variant->display_attributes)->mapWithKeys(fn (array $attribute) => [
                $attribute['attribute_id'] => $attribute['value_id'],
            ]),
            'regularPrice' => (string) $variant->current_regular_price,
            'salePrice' => $variant->current_sale_price !== null ? (string) $variant->current_sale_price : null,
            'displayRegularPrice' => $variant->display_regular_price,
            'displaySalePrice' => $variant->display_sale_price,
            'images' => $variant->display_image_urls,
        ])->values();

        return view('storefront.product-details', [
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'product' => $product,
            'category' => $category,
            'categoryName' => $categoryName,
            'brandName' => $brandName,
            'attributeGroups' => $attributeGroups,
            'variantOptions' => $variantOptions,
            'reviews' => $reviews,
            'reviewableOrderItems' => $reviewableOrderItems,
            'isFavorite' => $isFavorite,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    private function localized(Collection $translations, string $locale): mixed
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
}
