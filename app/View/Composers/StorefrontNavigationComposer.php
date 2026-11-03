<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Services\Storefront\CartManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontNavigationComposer
{
    public function __construct(
        private readonly CartManagementService $carts,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        $locale = app()->getLocale();
        $categories = Category::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $prepareNode = function (Category $category) use (&$prepareNode, $categories, $locale): array {
            $translation = $category->translations->firstWhere('locale', $locale)
                ?? $category->translations->firstWhere('locale', 'en')
                ?? $category->translations->first();

            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $translation?->name ?? $category->slug,
                'children' => $categories->where('parent_id', $category->id)
                    ->map(fn (Category $child) => $prepareNode($child))
                    ->values(),
            ];
        };

        $view->with('navigationCategories', $categories
            ->whereNull('parent_id')
            ->map(fn (Category $category) => $prepareNode($category))
            ->values());
        $view->with('cartItemCount', $this->carts->itemCount($this->request));
    }
}
