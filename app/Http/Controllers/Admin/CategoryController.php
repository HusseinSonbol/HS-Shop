<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Admin\CategoryManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'in:active,archived'],
        ]);
        $query = Category::query()
            ->when(($filters['scope'] ?? 'active') === 'archived', fn ($query) => $query->onlyTrashed())
            ->with('translations')
            ->when(! empty($filters['q']), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($search) => $search->where('slug', 'like', $term)
                    ->orWhereHas('translations', fn ($translations) => $translations->where('name', 'like', $term)));
            });
        $categories = $query->orderBy('sort_order')->orderBy('id')->get();
        $loadedIds = $categories->modelKeys();
        $groups = $categories->groupBy(fn (Category $category) => $category->parent_id && in_array($category->parent_id, $loadedIds, true) ? $category->parent_id : 0);
        $buildTree = function (int $parentId = 0) use (&$buildTree, $groups) {
            return $groups->get($parentId, collect())->map(function (Category $category) use (&$buildTree) {
                $category->setRelation('tree_children', $buildTree($category->id));

                return $category;
            });
        };

        return view('admin.categories.index', [
            'categories' => $buildTree(),
            'filters' => $filters + ['scope' => 'active'],
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category, 'parentOptions' => $this->parentOptions()]);
    }

    public function store(CategoryRequest $request, CategoryManagementService $categories): RedirectResponse
    {
        $categories->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.created'));
    }

    public function edit(Category $category, CategoryManagementService $categories): View
    {
        $category->load('translations');

        return view('admin.categories.form', [
            'category' => $category,
            'parentOptions' => $this->parentOptions($categories->descendantIds($category->id), $category->id),
        ]);
    }

    public function update(CategoryRequest $request, Category $category, CategoryManagementService $categories): RedirectResponse
    {
        $categories->update($category, $request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.updated'));
    }

    public function archive(Category $category, CategoryManagementService $categories): RedirectResponse
    {
        $categories->archive($category, (int) request()->user()->id);

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.archived'));
    }

    public function restore(Category $category, CategoryManagementService $categories): RedirectResponse
    {
        abort_unless($category->trashed(), 404);
        $categories->restore($category, (int) request()->user()->id);

        return redirect()->route('admin.categories.index', ['scope' => 'archived'])->with('status', __('admin.categories.restored'));
    }

    /** @param list<int> $excluded @return \Illuminate\Database\Eloquent\Collection<int, Category> */
    private function parentOptions(array $excluded = [], ?int $categoryId = null)
    {
        if ($categoryId) {
            $excluded[] = $categoryId;
        }

        return Category::availableForSelection()->whereNotIn('id', $excluded)->with('translations')->orderBy('sort_order')->orderBy('id')->get();
    }
}
