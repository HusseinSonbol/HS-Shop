<?php

namespace App\Services\Admin;

use App\Models\AboutFaq;
use App\Models\Banner;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WebsiteContentManagementService
{
    public function initializeHomepageDefaults(int $actorId): void
    {
        DB::transaction(function () use ($actorId): void {
            foreach ([
                'new' => ['New arrivals', 'وصل حديثًا', 20],
                'popular' => ['Popular products', 'الأكثر شيوعًا', 40],
            ] as $code => [$titleEn, $titleAr, $sortOrder]) {
                $inserted = DB::table('product_collections')->insertOrIgnore(['code' => $code, 'is_active' => true, 'sort_order' => $sortOrder, 'created_at' => now(), 'updated_at' => now()]);
                $collection = ProductCollection::query()->where('code', $code)->firstOrFail();
                foreach (['ar' => $titleAr, 'en' => $titleEn] as $locale => $title) {
                    DB::table('product_collection_translations')->insertOrIgnore(['product_collection_id' => $collection->id, 'locale' => $locale, 'title' => $title, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($inserted) {
                    $this->log($collection, 'collection.created', $actorId, null, ['code' => $collection->code, 'sort_order' => $collection->sort_order]);
                }
            }
            $newId = ProductCollection::query()->where('code', 'new')->value('id');
            $popularId = ProductCollection::query()->where('code', 'popular')->value('id');
            $sections = [
                ['home_hero', 'banner_placement', null, 'home_hero', 10, 'Home hero', 'واجهة الهوم'],
                ['home_new_products', 'product_collection', $newId, null, 20, 'New arrivals', 'وصل حديثًا'],
                ['home_promo_top', 'banner_placement', null, 'home_promo_top', 30, 'Top promotional banner', 'بانر ترويجي علوي'],
                ['home_popular_products', 'product_collection', $popularId, null, 40, 'Popular products', 'الأكثر شيوعًا'],
                ['home_promo_middle', 'banner_placement', null, 'home_promo_middle', 50, 'Middle promotional banner', 'بانر ترويجي أوسط'],
            ];
            foreach ($sections as [$code, $type, $collectionId, $placement, $sortOrder, $headingEn, $headingAr]) {
                $inserted = DB::table('homepage_sections')->insertOrIgnore([
                    'code' => $code, 'section_type' => $type, 'product_collection_id' => $collectionId, 'banner_placement' => $placement,
                    'sort_order' => $sortOrder, 'is_enabled' => true, 'created_by' => $actorId, 'updated_by' => $actorId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $section = HomepageSection::query()->where('code', $code)->firstOrFail();
                foreach (['ar' => $headingAr, 'en' => $headingEn] as $locale => $heading) {
                    DB::table('homepage_section_translations')->insertOrIgnore(['homepage_section_id' => $section->id, 'locale' => $locale, 'heading' => $heading, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($inserted) {
                    $this->log($section, 'homepage_section.created', $actorId, null, ['code' => $section->code, 'sort_order' => $section->sort_order, 'is_enabled' => $section->is_enabled]);
                }
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function saveBanner(?Banner $banner, array $data, int $actorId): Banner
    {
        $newPath = isset($data['image']) && $data['image'] instanceof UploadedFile
            ? $data['image']->store('homepage-banners', 'public')
            : null;
        if (isset($data['image']) && $data['image'] instanceof UploadedFile && ! $newPath) {
            throw new \RuntimeException('Unable to store homepage banner image.');
        }
        $oldPath = null;

        try {
            $saved = DB::transaction(function () use ($banner, $data, $actorId, $newPath, &$oldPath): Banner {
                $record = $banner ? Banner::query()->whereKey($banner->id)->lockForUpdate()->firstOrFail() : new Banner;
                $oldPath = $record->image_path;
                $before = $banner ? $this->bannerSnapshot($record) : null;
                $record->fill([
                    'placement' => $data['placement'],
                    'image_path' => $newPath ?? $record->image_path,
                    'target_url' => filled($data['target_url'] ?? null) ? trim($data['target_url']) : null,
                    'sort_order' => (int) $data['sort_order'],
                    'is_active' => (bool) $data['is_active'],
                    'created_by' => $record->exists ? $record->created_by : $actorId,
                    'updated_by' => $actorId,
                ])->save();
                foreach (['ar', 'en'] as $locale) {
                    $record->translations()->updateOrCreate(['locale' => $locale], [
                        'title' => filled($data['translations'][$locale]['title'] ?? null) ? trim($data['translations'][$locale]['title']) : null,
                        'alt_text' => trim($data['translations'][$locale]['alt_text']),
                    ]);
                }
                $this->log($record, $banner ? 'banner.updated' : 'banner.created', $actorId, $before, $this->bannerSnapshot($record->fresh('translations')));

                return $record;
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $saved;
    }

    /** @param list<array{id: int, sort_order: int, is_enabled: bool}> $sections */
    public function updateSections(array $sections, int $actorId): void
    {
        DB::transaction(function () use ($sections, $actorId): void {
            $records = HomepageSection::query()->lockForUpdate()->get()->keyBy('id');
            $submittedIds = collect($sections)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($records->keys()->map(fn ($id) => (int) $id)->sort()->values()->all() !== $submittedIds->all()) {
                throw ValidationException::withMessages(['sections' => __('admin.homepage.validation.sections_changed')]);
            }
            foreach ($sections as $data) {
                $section = $records->get((int) $data['id']);
                $before = ['sort_order' => $section->sort_order, 'is_enabled' => $section->is_enabled];
                $section->update(['sort_order' => (int) $data['sort_order'], 'is_enabled' => (bool) $data['is_enabled'], 'updated_by' => $actorId]);
                $this->log($section, 'homepage_section.updated', $actorId, $before, ['sort_order' => $section->sort_order, 'is_enabled' => $section->is_enabled]);
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function addCollectionProduct(ProductCollection $collection, array $data, int $actorId): void
    {
        DB::transaction(function () use ($collection, $data, $actorId): void {
            $lockedCollection = ProductCollection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey((int) $data['product_id'])->where('status', 'published')->where('store_availability_status', 'available')->lockForUpdate()->firstOrFail();
            if ($lockedCollection->products()->whereKey($product->id)->exists()) {
                throw ValidationException::withMessages(['product_id' => __('admin.homepage.validation.product_already_added')]);
            }
            $position = (int) ($lockedCollection->products()->max('position') ?? 0) + 1;
            DB::table('product_collection_items')->insert([
                'product_collection_id' => $lockedCollection->id,
                'product_id' => $product->id,
                'position' => $position,
                'added_by' => $actorId,
                'added_at' => now(),
            ]);
            $this->log($lockedCollection, 'collection.product_added', $actorId, null, ['product_id' => $product->id, 'position' => $position]);
        });
    }

    /** @param list<array{id: int, position: int}> $items */
    public function reorderCollection(ProductCollection $collection, array $items, int $actorId): void
    {
        DB::transaction(function () use ($collection, $items, $actorId): void {
            $locked = ProductCollection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('product_collection_items')->where('product_collection_id', $locked->id)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $submitted = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($existing->all() !== $submitted->all()) {
                throw ValidationException::withMessages(['items' => __('admin.homepage.validation.collection_changed')]);
            }
            $offset = (int) DB::table('product_collection_items')->where('product_collection_id', $locked->id)->max('position') + count($items) + 1;
            foreach ($items as $item) {
                DB::table('product_collection_items')->where('product_collection_id', $locked->id)->where('id', (int) $item['id'])->update(['position' => $offset + (int) $item['position']]);
            }
            foreach ($items as $item) {
                DB::table('product_collection_items')->where('product_collection_id', $locked->id)->where('id', (int) $item['id'])->update(['position' => (int) $item['position']]);
            }
            $this->log($locked, 'collection.reordered', $actorId, null, ['item_ids' => collect($items)->sortBy('position')->pluck('id')->values()->all()]);
        });
    }

    public function removeCollectionProduct(ProductCollection $collection, int $itemId, int $actorId): void
    {
        DB::transaction(function () use ($collection, $itemId, $actorId): void {
            $item = DB::table('product_collection_items')->where('product_collection_id', $collection->id)->where('id', $itemId)->lockForUpdate()->first();
            abort_if(! $item, 404);
            DB::table('product_collection_items')->where('id', $itemId)->delete();
            $this->log($collection, 'collection.product_removed', $actorId, ['product_id' => $item->product_id, 'position' => $item->position], null);
        });
    }

    /** @param array<string, mixed> $data */
    public function saveFaq(?AboutFaq $faq, array $data, int $actorId): AboutFaq
    {
        return DB::transaction(function () use ($faq, $data, $actorId): AboutFaq {
            $record = $faq ? AboutFaq::query()->whereKey($faq->id)->lockForUpdate()->firstOrFail() : new AboutFaq;
            $before = $faq ? $this->faqSnapshot($record) : null;
            $record->fill([
                'sort_order' => (int) $data['sort_order'],
                'is_active' => (bool) $data['is_active'],
                'created_by' => $record->exists ? $record->created_by : $actorId,
                'updated_by' => $actorId,
            ])->save();
            foreach (['ar', 'en'] as $locale) {
                $record->translations()->updateOrCreate(['locale' => $locale], [
                    'question' => trim($data['translations'][$locale]['question']),
                    'answer' => trim($data['translations'][$locale]['answer']),
                ]);
            }
            $this->log($record, $faq ? 'about_faq.updated' : 'about_faq.created', $actorId, $before, $this->faqSnapshot($record->fresh('translations')));

            return $record;
        });
    }

    public function archiveFaq(AboutFaq $faq, int $actorId): void
    {
        DB::transaction(function () use ($faq, $actorId): void {
            $faq = AboutFaq::query()->whereKey($faq->id)->lockForUpdate()->firstOrFail();
            $before = $this->faqSnapshot($faq->load('translations'));
            $faq->update(['updated_by' => $actorId]);
            $faq->delete();
            $this->log($faq, 'about_faq.archived', $actorId, $before, null);
        });
    }

    public function restoreFaq(AboutFaq $faq, int $actorId): void
    {
        DB::transaction(function () use ($faq, $actorId): void {
            $faq = AboutFaq::onlyTrashed()->whereKey($faq->id)->lockForUpdate()->firstOrFail();
            $faq->restore();
            $faq->update(['updated_by' => $actorId]);
            $this->log($faq, 'about_faq.restored', $actorId, null, $this->faqSnapshot($faq->fresh('translations')));
        });
    }

    /** @return array<string, mixed> */
    private function bannerSnapshot(Banner $banner): array
    {
        return ['placement' => $banner->placement, 'image_path' => $banner->image_path, 'target_url' => $banner->target_url, 'sort_order' => $banner->sort_order, 'is_active' => $banner->is_active,
            'translations' => $banner->translations->mapWithKeys(fn ($row) => [$row->locale => ['title' => $row->title, 'alt_text' => $row->alt_text]])->all()];
    }

    /** @return array<string, mixed> */
    private function faqSnapshot(AboutFaq $faq): array
    {
        return ['sort_order' => $faq->sort_order, 'is_active' => $faq->is_active, 'translations' => $faq->translations->mapWithKeys(fn ($row) => [$row->locale => ['question' => $row->question, 'answer' => $row->answer]])->all()];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(object $subject, string $event, int $actorId, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
