<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Admin\ProductManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'in:active,archived'],
            'status' => ['nullable', 'in:draft,published'],
        ]);
        $products = Product::query()
            ->when(($filters['scope'] ?? 'active') === 'archived', fn ($query) => $query->onlyTrashed())
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['q']), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($search) => $search->where('sku', 'like', $term)->orWhere('slug', 'like', $term)
                    ->orWhereHas('translations', fn ($translations) => $translations->where('name', 'like', $term)));
            })
            ->with(['translations', 'images' => fn ($images) => $images->where('is_primary', true)])
            ->withCount('variants')->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('admin.products.index', ['products' => $products, 'filters' => $filters + ['scope' => 'active']]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'formVariants' => [$this->emptyVariant()],
            ...$this->selectOptions(),
        ]);
    }

    public function store(ProductRequest $request, ProductManagementService $products): RedirectResponse
    {
        $product = $products->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.products.show', $product)->with('status', __('admin.products.created'));
    }

    public function show(Product $product): View
    {
        $product->load([
            'translations', 'brand.translations', 'categories.translations', 'images',
            'variants.attributes.translations', 'variants.attributes.values.translations',
            'variants.priceHistory.recordedBy',
            'supplierOptions.supplier', 'availabilityHistory.checkedBy',
        ]);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load(['translations', 'categories', 'images', 'variants.attributes.values.translations', 'variants.currentPriceVersion']);
        abort_if($product->trashed(), 404);

        $formVariants = $product->variants->map(function (ProductVariant $variant): array {
            $attributes = [];
            foreach ($variant->attributes as $attribute) {
                $value = $attribute->values->firstWhere('id', $attribute->pivot->attribute_value_id);
                $translated = $value?->translations->keyBy('locale');
                $attributes[$attribute->code.'_ar'] = $translated?->get('ar')?->value;
                $attributes[$attribute->code.'_en'] = $translated?->get('en')?->value;
            }

            return array_merge([
                'id' => $variant->id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'variant_name' => $variant->variant_name,
                'current_regular_price' => $variant->current_regular_price,
                'current_sale_price' => $variant->current_sale_price,
                'factory_unit_cost' => $variant->currentPriceVersion?->factory_unit_cost,
                'inbound_delivery_unit_cost' => $variant->currentPriceVersion?->inbound_delivery_unit_cost,
                'packaging_unit_cost' => $variant->currentPriceVersion?->packaging_unit_cost,
                'advertising_unit_cost' => $variant->currentPriceVersion?->advertising_unit_cost,
                'profit_rate_percent' => $variant->currentPriceVersion?->profit_rate_percent,
                'change_reason' => '',
                'weight_g' => $variant->weight_g,
                'is_active' => $variant->is_active,
                'is_default' => $variant->is_default,
            ], $attributes);
        })->all();

        return view('admin.products.form', [
            'product' => $product,
            'formVariants' => $formVariants ?: [$this->emptyVariant()],
            'selectedCategoryIds' => $this->directCategoryIds($product),
            ...$this->selectOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductManagementService $products): RedirectResponse
    {
        $products->update($product, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.products.show', $product)->with('status', __('admin.products.updated'));
    }

    public function archive(Product $product, ProductManagementService $products): RedirectResponse
    {
        $products->archive($product, (int) request()->user()->id);

        return redirect()->route('admin.products.index')->with('status', __('admin.products.archived'));
    }

    public function restore(Product $product, ProductManagementService $products): RedirectResponse
    {
        abort_unless($product->trashed(), 404);
        $products->restore($product, (int) request()->user()->id);

        return redirect()->route('admin.products.index', ['scope' => 'archived'])->with('status', __('admin.products.restored'));
    }

    /** @return array<string, mixed> */
    private function selectOptions(): array
    {
        $categories = Category::availableForSelection()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
        $categoriesById = $categories->keyBy('id');
        $categoryLabels = [];
        foreach ($categories as $category) {
            $segments = [];
            $current = $category;
            $visited = [];
            while ($current && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $segments[] = $current->translations->firstWhere('locale', app()->getLocale())?->name
                    ?? $current->translations->firstWhere('locale', 'ar')?->name
                    ?? $current->slug;
                $current = $categoriesById->get($current->parent_id);
            }
            $categoryLabels[$category->id] = implode(' / ', array_reverse($segments));
        }

        return [
            'categories' => $categories,
            'categoryLabels' => $categoryLabels,
            'brands' => Brand::query()->where('is_active', true)->with('translations')->orderBy('id')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyVariant(): array
    {
        return [
            'sku' => '', 'barcode' => '', 'variant_name' => '', 'size_ar' => '', 'size_en' => '', 'color_ar' => '', 'color_en' => '',
            'current_regular_price' => '', 'current_sale_price' => '', 'factory_unit_cost' => '', 'inbound_delivery_unit_cost' => '',
            'packaging_unit_cost' => '', 'advertising_unit_cost' => '', 'profit_rate_percent' => '', 'change_reason' => '',
            'weight_g' => '', 'is_active' => true, 'is_default' => true,
        ];
    }

    /** @return list<int> */
    private function directCategoryIds(Product $product): array
    {
        $selectedIds = $product->categories->modelKeys();
        $categories = Category::withTrashed()->get(['id', 'parent_id'])->keyBy('id');

        return array_values(array_filter($selectedIds, function (int $candidateId) use ($selectedIds, $categories): bool {
            foreach ($selectedIds as $otherId) {
                if ($otherId === $candidateId) {
                    continue;
                }
                $current = $categories->get($otherId);
                $visited = [];
                while ($current && $current->parent_id && ! isset($visited[$current->id])) {
                    $visited[$current->id] = true;
                    if ((int) $current->parent_id === $candidateId) {
                        return false;
                    }
                    $current = $categories->get($current->parent_id);
                }
            }

            return true;
        }));
    }
}
