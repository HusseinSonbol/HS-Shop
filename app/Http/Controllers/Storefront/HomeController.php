<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CustomerFeedback;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductReview;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();
        $now = now();

        $sections = HomepageSection::query()
            ->with(['translations', 'collection.translations'])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $banners = Banner::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('placement')
            ->map(fn (Collection $placementBanners) => $placementBanners->map(function (Banner $banner) use ($locale): Banner {
                $translation = $banner->translations->firstWhere('locale', $locale)
                    ?? $banner->translations->firstWhere('locale', 'en');

                $banner->setAttribute('display_title', $translation?->title);
                $banner->setAttribute('display_alt', $translation?->alt_text);
                $banner->setAttribute('display_image_url', Storage::disk('public')->url($banner->image_path));

                return $banner;
            }));

        $newSection = $sections->get('home_new_products');
        $popularSection = $sections->get('home_popular_products');
        $testimonialSection = $sections->get('home_testimonials');
        $testimonials = collect($testimonialSection?->settings['testimonials'] ?? [])
            ->filter(fn ($item) => (bool) ($item['is_active'] ?? false))
            ->sortBy('sort_order')
            ->values()
            ->map(function (array $item) use ($locale): array {
                $translation = $item['translations'][$locale] ?? $item['translations']['en'] ?? [];
                $item['display_name'] = $translation['name'] ?? '';
                $item['display_message'] = $translation['message'] ?? '';
                $item['display_image_url'] = Storage::disk('public')->url($item['image_path']);

                return $item;
            });

        $newProducts = $newSection?->collection
            ? $this->productsFromCollection($newSection->collection, $locale)
            : collect();
        $popularProducts = $popularSection?->collection
            ? $this->productsFromCollection($popularSection->collection, $locale)
            : collect();

        $storeProducts = $newProducts->merge($popularProducts)->unique('id');
        $customer = Auth::guard('customer')->user()?->customer;
        $favoriteIds = $customer && $storeProducts->isNotEmpty()
            ? $customer->favorites()->whereIn('products.id', $storeProducts->pluck('id'))->pluck('products.id')->flip()
            : collect();
        $storeProducts->each(fn (Product $product) => $product->setAttribute('is_favorite', $favoriteIds->has($product->id)));

        $showNewProducts = $newSection?->is_enabled && $newProducts->isNotEmpty();
        $showPopularProducts = $popularSection?->is_enabled && $popularProducts->isNotEmpty();

        $productsByCollection = collect();
        if ($newSection?->product_collection_id) {
            $productsByCollection->put($newSection->product_collection_id, $showNewProducts ? $newProducts : collect());
        }
        if ($popularSection?->product_collection_id) {
            $productsByCollection->put($popularSection->product_collection_id, $showPopularProducts ? $popularProducts : collect());
        }

        $reviews = ProductReview::query()
            ->where('moderation_status', 'approved')
            ->where('visibility_status', 'visible')
            ->whereHas('product', fn (Builder $query) => $query
                ->where('status', 'published')
                ->where('store_availability_status', 'available'))
            ->with(['product.translations', 'customer'])
            ->latest('reviewed_at')
            ->limit(6)
            ->get();

        $feedback = CustomerFeedback::query()
            ->where('moderation_status', 'approved')
            ->where('visibility_status', 'visible')
            ->where('show_on_homepage', true)
            ->latest('reviewed_at')
            ->limit(6)
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(function (Category $category) use ($locale): Category {
                $translation = $category->translations->firstWhere('locale', $locale)
                    ?? $category->translations->firstWhere('locale', 'en');
                $displayName = $translation?->name ?? $category->slug;
                $category->setAttribute('display_name', $displayName);
                $category->setAttribute(
                    'display_description',
                    $translation?->description ?: __('store.categories.description_fallback', ['name' => $displayName]),
                );

                $category->setAttribute(
                    'display_image_url',
                    $category->image_path ? Storage::disk('public')->url($category->image_path) : null,
                );

                return $category;
            });

        return view('storefront.index', [
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'banners' => $banners,
            'orderedSections' => $sections->where('is_enabled', true)->sortBy('sort_order')->values(),
            'productsByCollection' => $productsByCollection,
            'heroEnabled' => ! $sections->has('home_hero') || (bool) $sections->get('home_hero')->is_enabled,
            'reviews' => $reviews,
            'feedback' => $feedback,
            'testimonials' => $testimonials,
            'categories' => $categories,
        ]);
    }

    /** @return Collection<int, Product> */
    private function productsFromCollection(ProductCollection $collection, string $locale): Collection
    {
        if (! $collection->is_active) {
            return collect();
        }

        return $collection->products()
            ->where('products.status', 'published')
            ->where('products.store_availability_status', 'available')
            ->whereNotNull('products.published_at')
            ->where('products.published_at', '<=', now())
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->with([
                'translations',
                'images',
                'variants' => fn ($query) => $query->where('is_active', true),
            ])
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => $this->prepareProduct($product, $locale));
    }

    private function prepareProduct(Product $product, string $locale): Product
    {
        $translation = $product->translations->firstWhere('locale', $locale)
            ?? $product->translations->firstWhere('locale', 'en');
        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        $product->setAttribute('display_name', $translation?->name ?? $product->sku);
        $product->setAttribute('display_variant', $variant);
        $product->setAttribute('display_image_url', $this->imageUrl($product));

        if ($variant) {
            $variant->setAttribute('display_regular_price', $this->formatMoney($variant->current_regular_price));
            $variant->setAttribute('display_sale_price', $this->formatMoney($variant->current_sale_price));
        }

        return $product;
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

    private function imageUrl(Product $product): ?string
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return $image ? Storage::disk('public')->url($image->path) : null;
    }
}
