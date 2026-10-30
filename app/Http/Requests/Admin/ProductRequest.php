<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Support\ProductVariantSku;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $product = $this->route('product');
        $productType = (string) $this->input('product_type', $product instanceof Product ? $product->product_type : 'variable');
        if ($product instanceof Product) {
            $this->merge(['sku' => $product->sku, 'slug' => $product->slug]);
        }

        if (! $product instanceof Product) {
            $englishName = trim((string) $this->input('translations.en.name', ''));
            if ($englishName !== '') {
                $baseSlug = Str::limit(Str::slug($englishName) ?: 'product', 180, '');
                $baseSlug = rtrim($baseSlug, '-');
                $suffix = 1;
                do {
                    $tail = $suffix === 1 ? '' : '-'.$suffix;
                    $slug = Str::limit($baseSlug, 180 - strlen($tail), '').$tail;
                    $sku = Str::upper(Str::limit($baseSlug, 80 - strlen($tail), '')).Str::upper($tail);
                    $exists = DB::table('products')->where('slug', $slug)->orWhere('sku', $sku)->exists();
                    $suffix++;
                } while ($exists);

                $this->merge(['sku' => $sku, 'slug' => $slug]);
            }
        }

        $variants = $this->input('variants', []);
        if (! is_array($variants)) {
            return;
        }

        $variants = array_values($variants);
        $productSku = $product instanceof Product ? $product->sku : (string) $this->input('sku', 'PRODUCT');
        $usedSkus = [];
        foreach (array_values($variants) as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $baseSku = ProductVariantSku::base($productSku, $productType, $variant, $index + 1);
            $sku = $baseSku;
            $suffix = 1;
            do {
                $variantId = $variant['id'] ?? null;
                $exists = isset($usedSkus[$sku]) || DB::table('product_variants')->where('sku', $sku)
                    ->when($variantId, fn ($query) => $query->where('id', '<>', $variantId))->exists();
                if ($exists) {
                    $suffix++;
                    $tail = '-'.$suffix;
                    $sku = Str::limit($baseSku, 80 - strlen($tail), '').$tail;
                }
            } while ($exists);

            $variant['sku'] = $sku;
            $variants[$index] = $variant;
            $usedSkus[$sku] = true;
        }

        $this->merge(['variants' => $variants]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('catalog.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $product = $this->route('product');
        $product = $product instanceof Product ? $product : null;

        return [
            'product_type' => ['required', Rule::in(['simple', 'variable'])],
            'sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product?->id)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'store_availability_status' => ['required', Rule::in(['available', 'unavailable'])],
            'requires_shipping' => ['required', 'boolean'],
            'weight_g' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'category_ids' => ['required', 'array', 'min:1', 'max:20'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))],
            'translations' => ['required', 'array'],
            'translations.ar' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.ar.name' => ['required', 'string', 'max:220'],
            'translations.en.name' => ['required', 'string', 'max:220'],
            'translations.*.short_description' => ['nullable', 'string', 'max:500'],
            'translations.*.description' => ['nullable', 'string', 'max:50000'],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:80', 'distinct'],
            'variants.*.variant_name' => ['nullable', 'string', 'max:220'],
            'variants.*.size_ar' => ['nullable', 'string', 'max:160'],
            'variants.*.size_en' => ['nullable', 'string', 'max:160'],
            'variants.*.color_ar' => ['nullable', 'string', 'max:160'],
            'variants.*.color_en' => ['nullable', 'string', 'max:160'],
            'variants.*.current_regular_price' => ['required', 'numeric', 'gt:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.current_sale_price' => ['nullable', 'numeric', 'gt:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.factory_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.inbound_delivery_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.packaging_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.advertising_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'variants.*.change_reason' => ['nullable', 'string', 'max:255'],
            'variants.*.weight_g' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'distinct'],
            'primary_image_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $variants = $this->input('variants', []);
            if (! is_array($variants)) {
                return;
            }

            $type = $this->input('product_type');
            if ($type === 'simple' && count($variants) !== 1) {
                $validator->errors()->add('variants', __('admin.products.validation.simple_one_variant'));
            }
            if ($type === 'variable' && count($variants) < 1) {
                $validator->errors()->add('variants', __('admin.products.validation.variable_variant_required'));
            }
            $defaultCount = 0;
            $variantCombinations = [];
            foreach ($variants as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                if (($variant['is_default'] ?? false) === true || in_array($variant['is_default'] ?? null, ['1', 1, 'true'], true)) {
                    $defaultCount++;
                }
                if (filled($variant['current_sale_price'] ?? null) && is_numeric($variant['current_regular_price'] ?? null)
                    && $this->compareDecimals((string) $variant['current_sale_price'], (string) $variant['current_regular_price']) >= 0) {
                    $validator->errors()->add("variants.$index.current_sale_price", __('admin.products.validation.sale_must_be_lower'));
                }
                if ($type === 'variable' && ! (filled($variant['size_ar'] ?? null) || filled($variant['color_ar'] ?? null))) {
                    $validator->errors()->add("variants.$index.size_ar", __('admin.products.validation.variant_attribute_required'));
                }
                if ($type === 'variable' && filled($variant['size_ar'] ?? null) && ! filled($variant['size_en'] ?? null)) {
                    $validator->errors()->add("variants.$index.size_en", __('admin.products.validation.attribute_translation_required'));
                }
                if ($type === 'variable' && filled($variant['color_ar'] ?? null) && ! filled($variant['color_en'] ?? null)) {
                    $validator->errors()->add("variants.$index.color_en", __('admin.products.validation.attribute_translation_required'));
                }
                if ($type === 'variable') {
                    $combination = mb_strtolower(trim((string) ($variant['size_ar'] ?? ''))).'|'.mb_strtolower(trim((string) ($variant['color_ar'] ?? '')));
                    if (isset($variantCombinations[$combination])) {
                        $validator->errors()->add("variants.$index.size_ar", __('admin.products.validation.duplicate_variant_combination'));
                    }
                    $variantCombinations[$combination] = true;
                }

                $costFields = ['factory_unit_cost', 'inbound_delivery_unit_cost', 'packaging_unit_cost', 'advertising_unit_cost'];
                $providedCostFields = array_filter($costFields, fn ($field) => filled($variant[$field] ?? null));
                $allCostFieldsProvided = count($providedCostFields) === count($costFields);
                if ($allCostFieldsProvided && collect($costFields)->every(fn ($field) => $this->normalizeDecimal((string) $variant[$field]) === '0.0000')) {
                    $validator->errors()->add("variants.$index.factory_unit_cost", __('admin.products.validation.cost_total_must_be_positive'));
                }
                foreach ($costFields as $field) {
                    if ($providedCostFields !== [] && ! filled($variant[$field] ?? null)) {
                        $validator->errors()->add("variants.$index.$field", __('admin.products.validation.cost_input_required'));
                    }
                }

                $product = $this->route('product');
                $variantId = $variant['id'] ?? null;
                $savedVariant = $product instanceof Product && $variantId
                    ? DB::table('product_variants')->where('product_id', $product->id)->where('id', $variantId)->first()
                    : null;
                $priceChanged = ! $savedVariant
                    || (is_numeric($variant['current_regular_price'] ?? null)
                        && $this->normalizeDecimal((string) $savedVariant->current_regular_price) !== $this->normalizeDecimal((string) $variant['current_regular_price']))
                    || $this->normalizeNullableDecimal($savedVariant->current_sale_price ?? null) !== $this->normalizeNullableDecimal($variant['current_sale_price'] ?? null);
                $currentPrice = $savedVariant?->current_price_version_id
                    ? DB::table('product_price_history')->where('id', $savedVariant->current_price_version_id)->first()
                    : null;
                $costsDiffer = $currentPrice && $allCostFieldsProvided && collect($costFields)->contains(fn ($field) => $this->normalizeDecimal((string) $currentPrice->{$field}) !== $this->normalizeDecimal((string) $variant[$field]));
                $needsPriceVersion = ! $savedVariant || $priceChanged || $costsDiffer || (! $currentPrice && $allCostFieldsProvided);
                if ((! $savedVariant || ($needsPriceVersion && ! $currentPrice) || $costsDiffer) && ! $allCostFieldsProvided) {
                    foreach ($costFields as $field) {
                        if (! filled($variant[$field] ?? null)) {
                            $validator->errors()->add("variants.$index.$field", __('admin.products.validation.cost_input_required'));
                        }
                    }
                }
                if ($needsPriceVersion && $savedVariant && ! filled($variant['change_reason'] ?? null)) {
                    $validator->errors()->add("variants.$index.change_reason", __('admin.products.validation.price_reason_required'));
                }
            }
            if ($defaultCount > 1) {
                $validator->errors()->add('variants', __('admin.products.validation.one_default_variant'));
            }

            foreach ($variants as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $variantId = $variant['id'] ?? null;
                $skuExists = DB::table('product_variants')->where('sku', strtoupper(trim((string) ($variant['sku'] ?? ''))))
                    ->when($variantId, fn ($query) => $query->where('id', '<>', $variantId))->exists();
                if ($skuExists) {
                    $validator->errors()->add("variants.$index.sku", __('admin.products.validation.variant_sku_unique'));
                }
                if (filled($variant['barcode'] ?? null)) {
                    $barcodeExists = DB::table('product_variants')->where('barcode', trim((string) $variant['barcode']))
                        ->when($variantId, fn ($query) => $query->where('id', '<>', $variantId))->exists();
                    if ($barcodeExists) {
                        $validator->errors()->add("variants.$index.barcode", __('admin.products.validation.barcode_unique'));
                    }
                }
            }

            $product = $this->route('product');
            if ($product instanceof Product) {
                foreach ((array) $this->input('variants', []) as $index => $variant) {
                    $variantId = is_array($variant) ? ($variant['id'] ?? null) : null;
                    if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
                        $validator->errors()->add("variants.$index.id", __('admin.products.validation.variant_invalid'));
                    }
                }
                $ownedImageIds = $product->images()->pluck('id')->all();
                foreach ((array) $this->input('remove_image_ids', []) as $imageId) {
                    if (! in_array((int) $imageId, $ownedImageIds, true)) {
                        $validator->errors()->add('remove_image_ids', __('admin.products.validation.image_invalid'));
                        break;
                    }
                }
                $primaryImageId = $this->input('primary_image_id');
                if ($primaryImageId && (! in_array((int) $primaryImageId, $ownedImageIds, true)
                    || in_array((int) $primaryImageId, array_map('intval', (array) $this->input('remove_image_ids', [])), true))) {
                    $validator->errors()->add('primary_image_id', __('admin.products.validation.image_invalid'));
                }
                $remainingImages = $product->images()->whereNotIn('id', (array) $this->input('remove_image_ids', []))->exists();
                if ($this->input('status') === 'published' && ! $remainingImages && ! $this->hasFile('images')) {
                    $validator->errors()->add('images', __('admin.products.validation.published_image_required'));
                }
            } elseif ($this->input('status') === 'published' && ! $this->hasFile('images')) {
                $validator->errors()->add('images', __('admin.products.validation.published_image_required'));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_type.in' => __('admin.products.validation.type_invalid'),
            'sku.required' => __('admin.products.validation.sku_required'), 'sku.regex' => __('admin.products.validation.sku_invalid'), 'sku.unique' => __('admin.products.validation.sku_unique'),
            'slug.required' => __('admin.products.validation.slug_required'), 'slug.regex' => __('admin.products.validation.slug_invalid'), 'slug.unique' => __('admin.products.validation.slug_unique'),
            'brand_id.exists' => __('admin.products.validation.brand_invalid'),
            'category_ids.required' => __('admin.products.validation.category_required'), 'category_ids.min' => __('admin.products.validation.category_required'), 'category_ids.*.exists' => __('admin.products.validation.category_invalid'),
            'translations.ar.name.required' => __('admin.products.validation.ar_name_required'), 'translations.en.name.required' => __('admin.products.validation.en_name_required'),
            'variants.required' => __('admin.products.validation.variant_required'), 'variants.min' => __('admin.products.validation.variant_required'),
            'variants.*.sku.required' => __('admin.products.validation.variant_sku_required'), 'variants.*.sku.unique' => __('admin.products.validation.variant_sku_unique'), 'variants.*.sku.regex' => __('admin.products.validation.sku_invalid'),
            'variants.*.barcode.unique' => __('admin.products.validation.barcode_unique'),
            'variants.*.current_regular_price.required' => __('admin.products.validation.regular_price_required'), 'variants.*.current_regular_price.gt' => __('admin.products.validation.price_invalid'), 'variants.*.current_regular_price.regex' => __('admin.products.validation.price_precision'),
            'variants.*.current_sale_price.gt' => __('admin.products.validation.price_invalid'), 'variants.*.current_sale_price.regex' => __('admin.products.validation.price_precision'),
            'variants.*.factory_unit_cost.regex' => __('admin.products.validation.price_precision'),
            'variants.*.inbound_delivery_unit_cost.regex' => __('admin.products.validation.price_precision'),
            'variants.*.packaging_unit_cost.regex' => __('admin.products.validation.price_precision'),
            'variants.*.advertising_unit_cost.regex' => __('admin.products.validation.price_precision'),
            'images.*.image' => __('admin.products.validation.image_invalid'), 'images.*.mimes' => __('admin.products.validation.image_type'), 'images.*.max' => __('admin.products.validation.image_size'),
        ];
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

        return strcmp(str_pad($leftFraction, 4, '0'), str_pad($rightFraction, 4, '0')) <=> 0;
    }

    private function normalizeDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 4, '0');
    }

    private function normalizeNullableDecimal(mixed $value): ?string
    {
        return filled($value) ? $this->normalizeDecimal((string) $value) : null;
    }
}
