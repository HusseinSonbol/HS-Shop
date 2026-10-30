@extends('admin.layouts.app')

@section('title', $product->exists ? __('admin.products.edit_title') : __('admin.products.create_title'))

@section('content')
    <section class="page-heading"><div><p class="eyebrow">{{ __('admin.products.navigation') }}</p><h1>{{ $product->exists ? __('admin.products.edit_title') : __('admin.products.create_title') }}</h1><p class="page-description">{{ __('admin.products.form_note') }}</p></div></section>
    <form data-product-form data-auto-identifiers="{{ $product->exists ? 'false' : 'true' }}" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card panel product-form">
        @csrf @if ($product->exists) @method('PUT') @endif
        <section class="product-form-section">
            <h2>{{ __('admin.products.general') }}</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="product_type">{{ __('admin.products.type') }}</label><select data-product-type id="product_type" name="product_type" class="form-select @error('product_type') is-invalid @enderror"><option value="simple" @selected(old('product_type', $product->product_type ?? 'variable') === 'simple')>{{ __('admin.products.types.simple') }}</option><option value="variable" @selected(old('product_type', $product->product_type ?? 'variable') === 'variable')>{{ __('admin.products.types.variable') }}</option></select>@error('product_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="sku">{{ __('admin.products.group_sku') }}</label><input id="sku" name="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $product->sku) }}" maxlength="80" readonly dir="ltr">@error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="slug">{{ __('admin.products.slug') }}</label><input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $product->slug) }}" maxlength="190" readonly dir="ltr">@error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @unless ($product->exists)<div class="col-12"><p class="form-text">{{ __('admin.products.auto_identifier_help') }}</p></div>@endunless
                <div class="col-md-4"><label class="form-label" for="brand_id">{{ __('admin.products.brand_optional') }}</label><select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror"><option value="">{{ __('admin.products.no_brand') }}</option>@foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand->id)>{{ $brand->translations->firstWhere('locale', app()->getLocale())?->name ?? $brand->translations->firstWhere('locale', 'ar')?->name ?? $brand->slug }}</option>@endforeach</select>@error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="status">{{ __('admin.products.status') }}</label><select id="status" name="status" class="form-select"><option value="draft" @selected(old('status', $product->status ?? 'draft') === 'draft')>{{ __('admin.products.draft') }}</option><option value="published" @selected(old('status', $product->status) === 'published')>{{ __('admin.products.published') }}</option></select></div>
                <div class="col-md-4"><label class="form-label" for="store_availability_status">{{ __('admin.products.availability') }}</label><select id="store_availability_status" name="store_availability_status" class="form-select"><option value="available" @selected(old('store_availability_status', $product->store_availability_status ?? 'unavailable') === 'available')>{{ __('admin.products.availability_values.available') }}</option><option value="unavailable" @selected(old('store_availability_status', $product->store_availability_status ?? 'unavailable') === 'unavailable')>{{ __('admin.products.availability_values.unavailable') }}</option></select></div>
                <div class="col-md-4"><label class="form-label" for="weight_g">{{ __('admin.products.weight') }}</label><input id="weight_g" type="number" name="weight_g" class="form-control" min="0" max="1000000" value="{{ old('weight_g', $product->weight_g) }}"></div>
                <div class="col-md-4"><label class="form-label d-block" for="requires_shipping">{{ __('admin.products.requires_shipping') }}</label><input type="hidden" name="requires_shipping" value="0"><input id="requires_shipping" class="form-check-input" type="checkbox" name="requires_shipping" value="1" @checked(old('requires_shipping', $product->exists ? $product->requires_shipping : true))></div>
            </div>
        </section>

        <section class="product-form-section">
            <h2>{{ __('admin.products.categories') }}</h2>
            <p class="form-text">{{ __('admin.products.category_help') }}</p>
            @php($selectedCategories = old('category_ids', $selectedCategoryIds ?? []))
            <div class="row g-2">@foreach ($categories as $category)<div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories))><span class="form-check-label">{{ $categoryLabels[$category->id] }}</span></label></div>@endforeach</div>
            @error('category_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror @error('category_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </section>

        <section class="product-form-section">
            <h2>{{ __('admin.products.translations') }}</h2>
            <div class="row g-3">@foreach (['ar' => 'arabic', 'en' => 'english'] as $locale => $language)
                @php($translation = $product->translations->firstWhere('locale', $locale))
                <fieldset class="col-md-6"><legend class="h6">{{ __('admin.products.'.$language) }}</legend>
                    <label class="form-label" for="product-name-{{ $locale }}">{{ __('admin.products.name') }}</label><input id="product-name-{{ $locale }}" @if ($locale === 'en') data-product-name-en @endif name="translations[{{ $locale }}][name]" class="form-control @error('translations.'.$locale.'.name') is-invalid @enderror" value="{{ old('translations.'.$locale.'.name', $translation?->name) }}" maxlength="220" required lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">@error('translations.'.$locale.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label mt-3" for="short-{{ $locale }}">{{ __('admin.products.short_description') }}</label><input id="short-{{ $locale }}" name="translations[{{ $locale }}][short_description]" class="form-control" value="{{ old('translations.'.$locale.'.short_description', $translation?->short_description) }}" maxlength="500" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"><div class="form-text">{{ __('admin.products.short_description_help') }}</div>
                    <label class="form-label mt-3" for="description-{{ $locale }}">{{ __('admin.products.description') }}</label><textarea id="description-{{ $locale }}" name="translations[{{ $locale }}][description]" class="form-control" rows="5" maxlength="50000" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('translations.'.$locale.'.description', $translation?->description) }}</textarea><div class="form-text">{{ __('admin.products.description_help') }}</div>
                </fieldset>
            @endforeach</div>
        </section>

        <section class="product-form-section">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3"><div><h2 class="mb-1">{{ __('admin.products.variants') }}</h2><p class="text-body-secondary small mb-0">{{ __('admin.products.variants_note') }}</p></div><button class="btn btn-primary primary-button product-add-variant" type="button" data-add-variant>{{ __('admin.products.add_variant') }}</button></div>
            @error('variants')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="d-grid gap-3" data-variant-rows data-next-index="{{ count(old('variants', $formVariants)) }}">
                @foreach (old('variants', $formVariants) as $index => $variant)
                    @include('admin.products.partials.variant-row', ['index' => $index, 'variant' => $variant])
                @endforeach
            </div>
            <template data-variant-template>@include('admin.products.partials.variant-row', ['index' => '__INDEX__', 'variant' => ['sku' => '', 'barcode' => '', 'variant_name' => '', 'size_ar' => '', 'size_en' => '', 'color_ar' => '', 'color_en' => '', 'current_regular_price' => '', 'current_sale_price' => '', 'factory_unit_cost' => '', 'inbound_delivery_unit_cost' => '', 'packaging_unit_cost' => '', 'advertising_unit_cost' => '', 'profit_rate_percent' => '', 'change_reason' => '', 'weight_g' => '', 'is_active' => true, 'is_default' => false]])</template>
        </section>

        <section class="product-form-section">
            <h2>{{ __('admin.products.images') }}</h2>
            @if ($product->exists && $product->images->isNotEmpty())<div class="row g-3 mb-3">@foreach ($product->images as $image)<div class="col-6 col-md-3"><div class="product-image-card"><img src="{{ Storage::disk('public')->url($image->path) }}" alt=""><label class="form-check mt-2"><input class="form-check-input" type="radio" name="primary_image_id" value="{{ $image->id }}" @checked((string) old('primary_image_id', $product->images->firstWhere('is_primary', true)?->id) === (string) $image->id)><span class="form-check-label">{{ __('admin.products.set_primary_image') }}</span></label><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image_ids[]" value="{{ $image->id }}"><span class="form-check-label">{{ __('admin.products.remove_image') }}</span></label></div></div>@endforeach</div>@error('primary_image_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror @endif
            <label class="form-label" for="images">{{ __('admin.products.add_images') }}</label><input id="images" type="file" name="images[]" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" multiple>@error('images')<div class="text-danger small mt-1">{{ $message }}</div>@enderror @error('images.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('admin.products.image_help') }}</div>
        </section>

        <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">{{ __('admin.products.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.products.save') }}</button></div>
    </form>
@endsection
