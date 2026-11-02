<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index(Request $request, ?string $slug = null): View
    {
        $locale = app()->getLocale();
        $category = $slug
            ? Category::query()->where('slug', $slug)->where('is_active', true)->with('translations')->firstOrFail()
            : null;

        $activeCategories = Category::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $categoryTree = $this->prepareCategoryTree($activeCategories, $locale);
        $childCategories = $category
            ? $activeCategories->where('parent_id', $category->id)->map(fn (Category $child) => $this->prepareCategory($child, $locale))->values()
            : collect();
        $categoryName = $category ? $this->translation($category->translations, $locale)?->name ?? $category->slug : null;
        $categoryDescription = $category ? $this->translation($category->translations, $locale)?->description : null;
        $categoryImage = $category?->image_path ? Storage::disk('public')->url($category->image_path) : null;

        $sort = in_array($request->query('sort'), ['newest', 'price_low', 'price_high', 'name'], true)
            ? $request->query('sort')
            : 'newest';
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 120));
        $categoryIds = $category ? $this->descendantIds($activeCategories, $category->id) : [];

        $productsQuery = Product::query()
            ->where('products.status', 'published')
            ->where('products.store_availability_status', 'available')
            ->whereNotNull('products.published_at')
            ->where('products.published_at', '<=', now())
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->when($category, fn (Builder $query) => $query->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds)))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('translations', fn (Builder $translationQuery) => $translationQuery
                ->where('locale', $locale)
                ->where('name', 'like', '%'.$search.'%')))
            ->with([
                'translations',
                'images',
                'variants' => fn ($query) => $query->where('is_active', true),
            ]);

        match ($sort) {
            'price_low' => $productsQuery->orderBy(ProductVariant::query()
                ->selectRaw('CASE WHEN current_sale_price IS NOT NULL AND current_sale_price < current_regular_price THEN current_sale_price ELSE current_regular_price END')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->limit(1)),
            'price_high' => $productsQuery->orderByDesc(ProductVariant::query()
                ->selectRaw('CASE WHEN current_sale_price IS NOT NULL AND current_sale_price < current_regular_price THEN current_sale_price ELSE current_regular_price END')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->limit(1)),
            'name' => $productsQuery->orderBy(DB::table('product_translations')
                ->select('name')
                ->whereColumn('product_translations.product_id', 'products.id')
                ->where('product_translations.locale', $locale)
                ->limit(1)),
            default => $productsQuery->orderByDesc('products.published_at')->orderByDesc('products.id'),
        };

        $products = $productsQuery->paginate(12)->withQueryString();
        $products->getCollection()->transform(fn (Product $product) => $this->prepareProduct($product, $locale));

        return view('storefront.category', [
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'category' => $category,
            'categoryName' => $categoryName,
            'categoryDescription' => $categoryDescription,
            'categoryImage' => $categoryImage,
            'categoryTree' => $categoryTree,
            'childCategories' => $childCategories,
            'products' => $products,
            'sort' => $sort,
            'search' => $search,
        ]);
    }

    /** @param Collection<int, Category> $categories
     *  @return Collection<int, array<string, mixed>>
     */
    private function prepareCategoryTree(Collection $categories, string $locale): Collection
    {
        $prepareNode = function (Category $category) use (&$prepareNode, $categories, $locale): array {
            $prepared = $this->prepareCategory($category, $locale);
            $prepared['children'] = $categories->where('parent_id', $category->id)
                ->map(fn (Category $child) => $prepareNode($child))
                ->values();

            return $prepared;
        };

        return $categories->whereNull('parent_id')->map(fn (Category $category) => $prepareNode($category))->values();
    }

    private function prepareCategory(Category $category, string $locale): array
    {
        $translation = $this->translation($category->translations, $locale);

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $translation?->name ?? $category->slug,
        ];
    }

    /** @param Collection<int, Category> $categories
     *  @return list<int>
     */
    private function descendantIds(Collection $categories, int $parentId): array
    {
        $ids = [$parentId];
        $parents = [$parentId];

        while ($parents !== []) {
            $parents = $categories->whereIn('parent_id', $parents)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = [...$ids, ...$parents];
        }

        return $ids;
    }

    private function prepareProduct(Product $product, string $locale): Product
    {
        $translation = $this->translation($product->translations, $locale);
        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        $product->setAttribute('display_name', $translation?->name ?? $product->sku);
        $product->setAttribute('display_variant', $variant);
        $product->setAttribute('display_image_url', $image ? Storage::disk('public')->url($image->path) : null);

        if ($variant) {
            $variant->setAttribute('display_regular_price', $this->formatMoney($variant->current_regular_price));
            $variant->setAttribute('display_sale_price', $this->formatMoney($variant->current_sale_price));
        }

        return $product;
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
}
