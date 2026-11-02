@extends('admin.layouts.app')

@php($isEditing = $testimonial !== null)
@section('title', $isEditing ? __('admin.homepage.testimonials.edit') : __('admin.homepage.testimonials.add'))

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ __('admin.homepage.title') }}</p><h1>{{ $isEditing ? __('admin.homepage.testimonials.edit') : __('admin.homepage.testimonials.add') }}</h1></div></section>
    <section class="card panel customer-list-panel"><form method="POST" action="{{ $isEditing ? route('admin.homepage.testimonials.update', $testimonial['id']) : route('admin.homepage.testimonials.store') }}" enctype="multipart/form-data" class="p-3 p-md-4">
        @csrf @if($isEditing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="image">{{ __('admin.homepage.testimonials.photo') }}</label>
                @if($isEditing)<div class="mb-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($testimonial['image_path']) }}" alt="" class="rounded-circle" style="width:88px;height:88px;object-fit:cover"></div>@endif
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" @required(! $isEditing)>
                <div class="form-text">{{ __('admin.homepage.image_help') }}</div>@error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3"><label class="form-label" for="sort_order">{{ __('admin.homepage.sort_order') }}</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $testimonial['sort_order'] ?? 0) }}" required>@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3 d-flex align-items-end"><input type="hidden" name="is_active" value="0"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $testimonial['is_active'] ?? true))><span class="form-check-label">{{ __('admin.homepage.enabled') }}</span></label></div>
            @foreach(['ar' => 'ar', 'en' => 'en'] as $locale => $key)
                @php($translation = $testimonial['translations'][$locale] ?? [])
                <div class="col-12"><h2 class="h5 mb-0">{{ __('admin.homepage.locales.'.$locale) }}</h2></div>
                <div class="col-md-6"><label class="form-label" for="name-{{ $locale }}">{{ __('admin.homepage.testimonials.name') }}</label><input id="name-{{ $locale }}" name="translations[{{ $locale }}][name]" maxlength="160" class="form-control @error('translations.'.$locale.'.name') is-invalid @enderror" value="{{ old('translations.'.$locale.'.name', $translation['name'] ?? '') }}" required dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">@error('translations.'.$locale.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="message-{{ $locale }}">{{ __('admin.homepage.testimonials.message') }}</label><textarea id="message-{{ $locale }}" name="translations[{{ $locale }}][message]" rows="4" maxlength="3000" class="form-control @error('translations.'.$locale.'.message') is-invalid @enderror" required dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('translations.'.$locale.'.message', $translation['message'] ?? '') }}</textarea>@error('translations.'.$locale.'.message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endforeach
            <div class="col-12 form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.homepage.index') }}">{{ __('admin.homepage.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.homepage.testimonials.save') }}</button></div>
        </div>
    </form></section>
@endsection
