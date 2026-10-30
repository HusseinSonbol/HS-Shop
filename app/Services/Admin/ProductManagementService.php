<?php

namespace App\Services\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductManagementService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId): Product
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($data, $actorId, &$storedPaths): Product {
                $product = Product::create($this->productAttributes($data, $actorId));
                $this->saveTranslations($product, $data['translations']);
                $this->syncCategories($product, $data['category_ids']);
                $this->saveVariants($product, $data['variants'], $actorId);
                $this->storeImages($product, $data['images'] ?? [], $storedPaths);
                $this->recordAvailability($product, $actorId, null, $data['store_availability_status']);
                $this->log($product, 'product.created', $actorId, null, $this->snapshot($product->fresh()));

                return $product->load(['translations', 'categories.translations', 'variants', 'images']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data, int $actorId): void
    {
        $storedPaths = [];
        $removedPaths = [];
        try {
            DB::transaction(function () use ($product, $data, $actorId, &$storedPaths, &$removedPaths): void {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $before = $this->snapshot($product);
                $oldAvailability = $product->store_availability_status;
                $productAttributes = $this->productAttributes($data, $actorId, $product);
                if ($oldAvailability !== $data['store_availability_status']) {
                    $productAttributes['availability_checked_at'] = now();
                    $productAttributes['availability_checked_by'] = $actorId;
                }
                $product->update($productAttributes);
                $this->saveTranslations($product, $data['translations']);
                $this->syncCategories($product, $data['category_ids']);
                $this->saveVariants($product, $data['variants'], $actorId);
                $removedPaths = $this->removeImages($product, $data['remove_image_ids'] ?? []);
                $this->storeImages($product, $data['images'] ?? [], $storedPaths);
                $this->selectPrimaryImage($product, $data['primary_image_id'] ?? null);
                $this->ensurePrimaryImage($product);
                $this->recordAvailability($product, $actorId, $oldAvailability, $data['store_availability_status']);
                $this->log($product, 'product.updated', $actorId, $before, $this->snapshot($product->fresh()));
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        Storage::disk('public')->delete($removedPaths);
    }

    public function archive(Product $product, int $actorId): void
    {
        DB::transaction(function () use ($product, $actorId): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($product);
            $product->delete();
            $this->log($product, 'product.archived', $actorId, $before, null);
        });
    }

    public function restore(Product $product, int $actorId): void
    {
        DB::transaction(function () use ($product, $actorId): void {
            $product = Product::onlyTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->restore();
            $this->log($product, 'product.restored', $actorId, null, $this->snapshot($product->fresh()));
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function productAttributes(array $data, int $actorId, ?Product $product = null): array
    {
        $attributes = [
            'brand_id' => $data['brand_id'] ?? null,
            'product_type' => $data['product_type'],
            'sku' => strtoupper(trim($data['sku'])),
            'slug' => strtolower(trim($data['slug'])),
            'status' => $data['status'],
            'store_availability_status' => $data['store_availability_status'],
            'requires_shipping' => (bool) $data['requires_shipping'],
            'weight_g' => $data['weight_g'] ?? null,
        ];
        if (! $product) {
            $attributes['availability_checked_at'] = now();
            $attributes['availability_checked_by'] = $actorId;
        }
        if ($data['status'] === 'published' && ! $product?->published_at) {
            $attributes['published_at'] = now();
        }

        return $attributes;
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function saveTranslations(Product $product, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $product->translations()->updateOrCreate(['locale' => $locale], [
                'name' => trim($translations[$locale]['name']),
                'short_description' => filled($translations[$locale]['short_description'] ?? null) ? trim($translations[$locale]['short_description']) : null,
                'description' => filled($translations[$locale]['description'] ?? null) ? trim($translations[$locale]['description']) : null,
            ]);
        }
    }

    /** @param list<int> $categoryIds */
    private function syncCategories(Product $product, array $categoryIds): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', $categoryIds)));
        $attachedIds = [];
        $primaryId = null;
        $primaryDepth = -1;
        foreach ($selectedIds as $categoryId) {
            $current = Category::query()->find($categoryId);
            $depth = 0;
            while ($current && $current->is_active) {
                $attachedIds[$current->id] = true;
                $depth++;
                if ($current->parent_id === null) {
                    break;
                }
                $current = Category::query()->find($current->parent_id);
            }
            if ($depth > $primaryDepth) {
                $primaryDepth = $depth;
                $primaryId = $categoryId;
            }
        }

        $pivot = [];
        foreach (array_keys($attachedIds) as $categoryId) {
            $pivot[$categoryId] = ['is_primary' => (int) $categoryId === $primaryId];
        }
        $product->categories()->sync($pivot);
    }

    /** @param list<array<string, mixed>> $variants */
    private function saveVariants(Product $product, array $variants, int $actorId): void
    {
        $keepIds = [];
        $hasExplicitDefault = collect($variants)->contains(fn (array $variant): bool => filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $defaultSet = false;
        foreach (array_values($variants) as $index => $data) {
            $variantId = isset($data['id']) ? (int) $data['id'] : null;
            $variant = $variantId ? $product->variants()->whereKey($variantId)->lockForUpdate()->firstOrFail() : new ProductVariant;
            $previousRegularPrice = $variantId ? (string) $variant->current_regular_price : null;
            $previousSalePrice = $variantId ? $variant->current_sale_price : null;
            $isExplicitDefault = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isDefault = $hasExplicitDefault ? $isExplicitDefault : ! $defaultSet;
            $variant->fill([
                'sku' => strtoupper(trim($data['sku'])),
                'barcode' => filled($data['barcode'] ?? null) ? trim($data['barcode']) : null,
                'variant_name' => filled($data['variant_name'] ?? null) ? trim($data['variant_name']) : null,
                'is_default' => $isDefault,
                'is_active' => (bool) $data['is_active'],
                'current_regular_price' => $this->decimal($data['current_regular_price']),
                'current_sale_price' => filled($data['current_sale_price'] ?? null) ? $this->decimal($data['current_sale_price']) : null,
                'currency' => 'EGP',
                'weight_g' => $data['weight_g'] ?? null,
            ]);
            if (! $variantId) {
                $product->variants()->save($variant);
            } else {
                $variant->save();
            }
            $defaultSet = $defaultSet || $isDefault;
            $keepIds[] = $variant->id;
            $this->recordPriceVersion($variant, $data, $actorId, $previousRegularPrice, $previousSalePrice);
            $this->syncVariantAttributes($variant, $product->product_type === 'variable' ? $data : []);
        }

        $removedVariants = $product->variants()->whereNotIn('id', $keepIds)->get();
        foreach ($removedVariants as $removedVariant) {
            DB::table('variant_attribute_values')->where('variant_id', $removedVariant->id)->delete();
            $removedVariant->delete();
        }
    }

    /** @param array<string, mixed> $data */
    private function recordPriceVersion(ProductVariant $variant, array $data, int $actorId, ?string $previousRegularPrice, ?string $previousSalePrice): void
    {
        $costFields = ['factory_unit_cost', 'inbound_delivery_unit_cost', 'packaging_unit_cost', 'advertising_unit_cost'];
        $allCostFieldsProvided = collect($costFields)->every(fn (string $field): bool => filled($data[$field] ?? null));
        $latestPrice = $variant->current_price_version_id
            ? ProductPriceHistory::query()->whereKey($variant->current_price_version_id)->lockForUpdate()->first()
            : null;
        $latestPrice ??= $variant->priceHistory()->lockForUpdate()->first();

        $priceChanged = $previousRegularPrice === null
            || $this->decimal($previousRegularPrice) !== $this->decimal($data['current_regular_price'])
            || $this->nullableDecimal($previousSalePrice) !== $this->nullableDecimal($data['current_sale_price'] ?? null);
        $costInputsChanged = $allCostFieldsProvided && (! $latestPrice || collect($costFields)->contains(fn (string $field): bool => $this->decimal((string) $latestPrice->{$field}) !== $this->decimal((string) $data[$field])));

        if (! $priceChanged && ! $costInputsChanged) {
            return;
        }

        $costs = [];
        foreach ($costFields as $field) {
            $value = $allCostFieldsProvided ? $data[$field] : $latestPrice?->{$field};
            if ($value === null) {
                throw ValidationException::withMessages([
                    'variants' => __('admin.products.validation.cost_input_required'),
                ]);
            }
            $costs[$field] = $this->decimal((string) $value);
        }

        $versionId = DB::table('product_price_history')->insertGetId([
            'product_variant_id' => $variant->id,
            'version_no' => (int) DB::table('product_price_history')->where('product_variant_id', $variant->id)->lockForUpdate()->max('version_no') + 1,
            'factory_unit_cost' => $costs['factory_unit_cost'],
            'inbound_delivery_unit_cost' => $costs['inbound_delivery_unit_cost'],
            'packaging_unit_cost' => $costs['packaging_unit_cost'],
            'advertising_unit_cost' => $costs['advertising_unit_cost'],
            'pricing_cost_total' => $this->sumDecimals(array_slice($costs, 0, 4, true)),
            'profit_rate_percent' => $this->calculateMarkupRate($variant->current_regular_price, $this->sumDecimals($costs)),
            'calculated_regular_price' => null,
            'official_regular_price' => $variant->current_regular_price,
            'sale_price' => $variant->current_sale_price,
            'currency' => 'EGP',
            'recorded_at' => now(),
            'recorded_by' => $actorId,
            'change_reason' => filled($data['change_reason'] ?? null) ? trim((string) $data['change_reason']) : null,
            'created_at' => now(),
        ]);

        $variant->forceFill(['current_price_version_id' => $versionId])->save();
    }

    /** @param array<string, mixed> $variantData */
    private function syncVariantAttributes(ProductVariant $variant, array $variantData): void
    {
        $this->syncVariantAttribute($variant, 'size', 'المقاس', 'Size', $variantData['size_ar'] ?? null, $variantData['size_en'] ?? null);
        $this->syncVariantAttribute($variant, 'color', 'اللون', 'Color', $variantData['color_ar'] ?? null, $variantData['color_en'] ?? null);
    }

    private function syncVariantAttribute(ProductVariant $variant, string $code, string $nameAr, string $nameEn, ?string $valueAr, ?string $valueEn): void
    {
        DB::table('variant_attribute_values')->where('variant_id', $variant->id)->whereIn('attribute_id', function ($query) use ($code): void {
            $query->select('id')->from('attributes')->where('code', $code);
        })->delete();
        if (! filled($valueAr) && ! filled($valueEn)) {
            return;
        }
        $valueAr = trim((string) ($valueAr ?: $valueEn));
        $valueEn = trim((string) ($valueEn ?: $valueAr));
        $attribute = Attribute::query()->firstOrCreate(['code' => $code], ['input_type' => 'select', 'is_variant_axis' => true, 'sort_order' => $code === 'size' ? 1 : 2]);
        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            $attribute->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
        }
        $valueCode = 'v-'.substr(hash('sha256', mb_strtolower($valueEn)), 0, 32);
        $value = AttributeValue::query()->firstOrCreate(['attribute_id' => $attribute->id, 'code' => $valueCode], ['sort_order' => 0]);
        $value->translations()->updateOrCreate(['locale' => 'ar'], ['value' => $valueAr]);
        $value->translations()->updateOrCreate(['locale' => 'en'], ['value' => $valueEn]);
        DB::table('variant_attribute_values')->insert([
            'variant_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => $value->id,
        ]);
    }

    /** @param list<UploadedFile> $images @param list<string> $storedPaths */
    private function storeImages(Product $product, array $images, array &$storedPaths): void
    {
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        foreach ($images as $image) {
            $path = $image->store('products/'.$product->id, 'public');
            if ($path === false) {
                throw new \RuntimeException('Unable to store product image.');
            }
            $storedPaths[] = $path;
            $product->images()->create([
                'path' => $path,
                'sort_order' => (int) $product->images()->max('sort_order') + 1,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
    }

    /** @param list<int> $imageIds @return list<string> */
    private function removeImages(Product $product, array $imageIds): array
    {
        if ($imageIds === []) {
            return [];
        }
        $images = $product->images()->whereIn('id', $imageIds)->get();
        $paths = $images->pluck('path')->all();
        foreach ($images as $image) {
            $image->delete();
        }

        return $paths;
    }

    private function ensurePrimaryImage(Product $product): void
    {
        if (! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
        }
    }

    private function selectPrimaryImage(Product $product, int|string|null $imageId): void
    {
        if (! $imageId) {
            return;
        }

        $product->images()->update(['is_primary' => false]);
        $product->images()->whereKey((int) $imageId)->update(['is_primary' => true]);
    }

    private function recordAvailability(Product $product, int $actorId, ?string $before, string $after): void
    {
        if ($before === $after) {
            return;
        }
        DB::table('product_availability_history')->insert([
            'product_id' => $product->id,
            'availability_status' => $after,
            'checked_at' => now(),
            'checked_by' => $actorId,
            'note' => null,
            'created_at' => now(),
        ]);
    }

    private function decimal(string|int $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 4, '0');
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return filled($value) ? $this->decimal((string) $value) : null;
    }

    /** @param array<string, string> $values */
    private function sumDecimals(array $values): string
    {
        $sum = '0';
        foreach ($values as $value) {
            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            $scaled = ltrim($whole.str_pad($fraction, 4, '0'), '0') ?: '0';
            $carry = 0;
            $result = '';
            $leftIndex = strlen($sum) - 1;
            $rightIndex = strlen($scaled) - 1;
            while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
                $digit = ($leftIndex >= 0 ? (int) $sum[$leftIndex--] : 0)
                    + ($rightIndex >= 0 ? (int) $scaled[$rightIndex--] : 0)
                    + $carry;
                $result = ($digit % 10).$result;
                $carry = intdiv($digit, 10);
            }
            $sum = ltrim($result, '0') ?: '0';
        }

        if (strlen($sum) > 19) {
            throw ValidationException::withMessages(['variants' => __('admin.products.validation.cost_total_overflow')]);
        }

        $scaled = str_pad($sum, 5, '0', STR_PAD_LEFT);
        $whole = ltrim(substr($scaled, 0, -4), '0') ?: '0';

        return $whole.'.'.substr($scaled, -4);
    }

    private function calculateMarkupRate(string $sellingPrice, string $totalCost): string
    {
        $price = $this->toScaledInteger($sellingPrice);
        $cost = $this->toScaledInteger($totalCost);
        if ($cost === '0') {
            throw ValidationException::withMessages(['variants' => __('admin.products.validation.cost_total_must_be_positive')]);
        }

        $negative = $this->compareIntegers($price, $cost) < 0;
        $difference = $negative ? $this->subtractIntegers($cost, $price) : $this->subtractIntegers($price, $cost);
        $numerator = $this->multiplyInteger($difference, 1000000);
        [$quotient, $remainder] = $this->divideIntegers($numerator, $cost);
        if ($this->compareIntegers($this->multiplyInteger($remainder, 2), $cost) >= 0) {
            $quotient = $this->addIntegers($quotient, '1');
        }
        if (strlen($quotient) > 9) {
            throw ValidationException::withMessages(['variants' => __('admin.products.validation.profit_rate_overflow')]);
        }

        $scaled = str_pad($quotient, 5, '0', STR_PAD_LEFT);
        $result = (ltrim(substr($scaled, 0, -4), '0') ?: '0').'.'.substr($scaled, -4);

        return $negative && $quotient !== '0' ? '-'.$result : $result;
    }

    private function toScaledInteger(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole.str_pad($fraction, 4, '0'), '0') ?: '0';
    }

    private function compareIntegers(string $left, string $right): int
    {
        return strlen($left) === strlen($right) ? (strcmp($left, $right) <=> 0) : (strlen($left) <=> strlen($right));
    }

    private function subtractIntegers(string $left, string $right): string
    {
        $borrow = 0;
        $result = '';
        for ($i = 1; $i <= strlen($left); $i++) {
            $digit = (int) $left[-$i] - ($i <= strlen($right) ? (int) $right[-$i] : 0) - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = (($digit + ($borrow ? 10 : 0)) % 10).$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private function addIntegers(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        for ($i = 1; $i <= max(strlen($left), strlen($right)) || $carry; $i++) {
            $digit = ($i <= strlen($left) ? (int) $left[-$i] : 0) + ($i <= strlen($right) ? (int) $right[-$i] : 0) + $carry;
            $result = ($digit % 10).$result;
            $carry = intdiv($digit, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function multiplyInteger(string $value, int $factor): string
    {
        $carry = 0;
        $result = '';
        for ($i = strlen($value) - 1; $i >= 0; $i--) {
            $product = (int) $value[$i] * $factor + $carry;
            $result = ($product % 10).$result;
            $carry = intdiv($product, 10);
        }
        while ($carry > 0) {
            $result = ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    /** @return array{string, string} */
    private function divideIntegers(string $dividend, string $divisor): array
    {
        $quotient = '';
        $remainder = '0';
        foreach (str_split($dividend) as $digit) {
            $remainder = ltrim(($remainder === '0' ? '' : $remainder).$digit, '0') ?: '0';
            $quotientDigit = 0;
            while ($this->compareIntegers($remainder, $this->multiplyInteger($divisor, $quotientDigit + 1)) >= 0) {
                $quotientDigit++;
            }
            if ($quotientDigit > 0) {
                $remainder = $this->subtractIntegers($remainder, $this->multiplyInteger($divisor, $quotientDigit));
            }
            $quotient .= (string) $quotientDigit;
        }

        return [ltrim($quotient, '0') ?: '0', $remainder];
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product): array
    {
        $product->loadMissing(['translations', 'categories', 'variants.currentPriceVersion', 'variants.attributes.translations', 'variants.attributes.values.translations', 'images']);

        return [
            'brand_id' => $product->brand_id,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'product_type' => $product->product_type,
            'status' => $product->status,
            'store_availability_status' => $product->store_availability_status,
            'requires_shipping' => $product->requires_shipping,
            'weight_g' => $product->weight_g,
            'translations' => $product->translations->mapWithKeys(fn ($translation) => [$translation->locale => [
                'name' => $translation->name,
                'short_description' => $translation->short_description,
                'description' => $translation->description,
            ]])->all(),
            'category_ids' => $product->categories->modelKeys(),
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'variant_name' => $variant->variant_name,
                'regular_price' => $variant->current_regular_price,
                'sale_price' => $variant->current_sale_price,
                'price_version' => $variant->currentPriceVersion ? [
                    'id' => $variant->currentPriceVersion->id,
                    'version_no' => $variant->currentPriceVersion->version_no,
                    'factory_unit_cost' => $variant->currentPriceVersion->factory_unit_cost,
                    'inbound_delivery_unit_cost' => $variant->currentPriceVersion->inbound_delivery_unit_cost,
                    'packaging_unit_cost' => $variant->currentPriceVersion->packaging_unit_cost,
                    'advertising_unit_cost' => $variant->currentPriceVersion->advertising_unit_cost,
                    'pricing_cost_total' => $variant->currentPriceVersion->pricing_cost_total,
                    'profit_rate_percent' => $variant->currentPriceVersion->profit_rate_percent,
                ] : null,
                'is_active' => $variant->is_active,
                'is_default' => $variant->is_default,
                'attributes' => $variant->attributes->map(function (Attribute $attribute): array {
                    $valueId = $attribute->pivot->attribute_value_id;
                    $value = $attribute->values->firstWhere('id', $valueId);

                    return [
                        'code' => $attribute->code,
                        'value' => $value?->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->value])->all() ?? [],
                    ];
                })->all(),
            ])->all(),
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'variant_id' => $image->variant_id,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
            ])->all(),
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(Product $product, string $event, int $actorId, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => Product::class,
            'subject_id' => $product->id,
            'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
