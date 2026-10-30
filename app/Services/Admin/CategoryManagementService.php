<?php

namespace App\Services\Admin;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId): Category
    {
        return DB::transaction(function () use ($data, $actorId): Category {
            $this->lockTree();
            $category = Category::create($this->attributes($data));
            $this->saveTranslations($category, $data['translations']);
            $this->log($category, 'created', $actorId, null, $this->snapshot($category));

            return $category->load('translations');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Category $category, array $data, int $actorId): void
    {
        DB::transaction(function () use ($category, $data, $actorId): void {
            $rows = $this->lockTree();
            $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($parentId && $this->wouldCreateCycleInRows($category->id, $parentId, $rows)) {
                throw ValidationException::withMessages(['parent_id' => __('admin.categories.validation.cycle')]);
            }
            if (! $data['is_active'] && Category::query()->whereIn('id', $this->descendantIds($category->id))->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['category' => __('admin.categories.errors.hide_children_first')]);
            }

            $before = $this->snapshot($category->load('translations'));
            $category->update($this->attributes($data));
            $this->saveTranslations($category, $data['translations']);
            $this->log($category, 'updated', $actorId, $before, $this->snapshot($category->fresh('translations')));
        });
    }

    public function archive(Category $category, int $actorId): void
    {
        DB::transaction(function () use ($category, $actorId): void {
            $this->lockTree();
            $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            if ($category->children()->exists()) {
                throw ValidationException::withMessages(['category' => __('admin.categories.errors.archive_children_first')]);
            }

            $before = $this->snapshot($category->load('translations'));
            $category->delete();
            $this->log($category, 'archived', $actorId, $before, null);
        });
    }

    public function restore(Category $category, int $actorId): void
    {
        DB::transaction(function () use ($category, $actorId): void {
            $this->lockTree();
            $category = Category::onlyTrashed()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            if ($category->parent_id && ! Category::query()->whereKey($category->parent_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['category' => __('admin.categories.errors.restore_parent_first')]);
            }

            $category->restore();
            $this->log($category, 'restored', $actorId, null, $this->snapshot($category->load('translations')));
        });
    }

    public function wouldCreateCycle(int $categoryId, int $parentId): bool
    {
        return $this->wouldCreateCycleInRows($categoryId, $parentId, DB::table('categories')->select('id', 'parent_id')->get());
    }

    /** @return list<int> */
    public function descendantIds(int $categoryId): array
    {
        $rows = DB::table('categories')->select('id', 'parent_id')->get();
        $children = $rows->groupBy('parent_id');
        $ids = [];
        $queue = [$categoryId];
        while ($queue !== []) {
            foreach ($children->get(array_shift($queue), collect()) as $child) {
                $id = (int) $child->id;
                if (! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }

    private function lockTree()
    {
        return DB::table('categories')->orderBy('id')->lockForUpdate()->get(['id', 'parent_id']);
    }

    private function wouldCreateCycleInRows(int $categoryId, int $parentId, iterable $rows): bool
    {
        if ($categoryId === $parentId) {
            return true;
        }

        $parents = [];
        foreach ($rows as $row) {
            $parents[(int) $row->id] = $row->parent_id === null ? null : (int) $row->parent_id;
        }
        $cursor = $parentId;
        $seen = [];
        while ($cursor !== null) {
            if ($cursor === $categoryId || isset($seen[$cursor])) {
                return true;
            }
            $seen[$cursor] = true;
            $cursor = $parents[$cursor] ?? null;
        }

        return false;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'parent_id' => $data['parent_id'] ?? null,
            'slug' => $data['slug'],
            'sort_order' => (int) $data['sort_order'],
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** @param array<string, array<string, string|null>> $translations */
    private function saveTranslations(Category $category, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $category->translations()->updateOrCreate(['locale' => $locale], [
                'name' => trim($translations[$locale]['name']),
                'description' => filled($translations[$locale]['description'] ?? null) ? trim($translations[$locale]['description']) : null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Category $category): array
    {
        return [
            'parent_id' => $category->parent_id,
            'slug' => $category->slug,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'translations' => $category->translations->mapWithKeys(fn ($translation) => [$translation->locale => [
                'name' => $translation->name,
                'description' => $translation->description,
            ]])->all(),
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(Category $category, string $action, int $actorId, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'subject_type' => Category::class,
            'subject_id' => $category->id,
            'event' => 'category.'.$action,
            'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
