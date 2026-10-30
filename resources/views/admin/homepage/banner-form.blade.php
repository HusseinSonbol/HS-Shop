@extends('admin.layouts.app')

@section('title', $banner->exists ? __('admin.homepage.edit_banner') : __('admin.homepage.add_banner'))

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ __('admin.homepage.title') }}</p><h1>{{ $banner->exists ? __('admin.homepage.edit_banner') : __('admin.homepage.add_banner') }}</h1></div></section>
    <section class="card panel customer-list-panel"><form method="POST" action="{{ $banner->exists ? route('admin.homepage.banners.update', $banner) : route('admin.homepage.banners.store') }}" enctype="multipart/form-data" class="p-3 p-md-4">
        @csrf @if($banner->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="placement">{{ __('admin.homepage.placement') }}</label><select id="placement" name="placement" class="form-select" required>@foreach(['home_hero', 'home_promo_top', 'home_promo_middle'] as $placement)<option value="{{ $placement }}" @selected(old('placement', $banner->placement) === $placement)>{{ __('admin.homepage.placements.'.$placement) }}</option>@endforeach</select>@error('placement')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="sort_order">{{ __('admin.homepage.sort_order') }}</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" class="form-control" value="{{ old('sort_order', $banner->sort_order ?? 0) }}" required>@error('sort_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="image">{{ __('admin.homepage.image') }}</label>@if($banner->exists)<div class="mb-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}" alt="" class="rounded" style="max-width:240px;max-height:140px;object-fit:cover"></div>@endif<input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"> <div class="form-text">{{ __('admin.homepage.image_help') }}</div>@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="target_url">{{ __('admin.homepage.target_url') }}</label><input id="target_url" name="target_url" type="text" maxlength="500" class="form-control" value="{{ old('target_url', $banner->target_url) }}" dir="ltr">@error('target_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            @foreach(['ar' => 'ar', 'en' => 'en'] as $locale => $key)
                @php($translation = $banner->translations->firstWhere('locale', $locale))
                <div class="col-12"><h2 class="h5 mb-0">{{ __('admin.homepage.locales.'.$locale) }}</h2></div>
                <div class="col-md-6"><label class="form-label" for="title-{{ $locale }}">{{ __('admin.homepage.banner_title') }}</label><input id="title-{{ $locale }}" name="translations[{{ $locale }}][title]" maxlength="255" class="form-control" value="{{ old('translations.'.$locale.'.title', $translation?->title) }}"></div>
                <div class="col-md-6"><label class="form-label" for="alt-{{ $locale }}">{{ __('admin.homepage.alt_text') }}</label><input id="alt-{{ $locale }}" name="translations[{{ $locale }}][alt_text]" maxlength="255" class="form-control" value="{{ old('translations.'.$locale.'.alt_text', $translation?->alt_text) }}" required dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">@error('translations.'.$locale.'.alt_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            @endforeach
            <div class="col-12"><input type="hidden" name="is_active" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))><span class="form-check-label">{{ __('admin.homepage.enabled') }}</span></label></div>
            <div class="col-12 form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.homepage.index') }}">{{ __('admin.homepage.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.homepage.save_banner') }}</button></div>
        </div>
    </form></section>
@endsection
