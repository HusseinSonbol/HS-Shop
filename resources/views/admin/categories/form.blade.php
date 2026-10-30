@extends('admin.layouts.app')

@section('title', $category->exists ? __('admin.categories.edit_title') : __('admin.categories.create_title'))

@section('content')
    <div class="mb-4"><h1 class="h3 mb-0">{{ $category->exists ? __('admin.categories.edit_title') : __('admin.categories.create_title') }}</h1></div>
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card card-body gap-4">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="slug">{{ __('admin.categories.slug') }}</label><input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $category->slug) }}" required maxlength="190" dir="ltr" placeholder="home-furnishings">@error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="parent_id">{{ __('admin.categories.parent') }}</label><select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror"><option value="">{{ __('admin.categories.no_parent') }}</option>@foreach ($parentOptions as $option)<option value="{{ $option->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $option->id)>{{ $option->translations->firstWhere('locale', app()->getLocale())?->name ?? $option->slug }}</option>@endforeach</select>@error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="sort_order">{{ __('admin.categories.order') }}</label><input id="sort_order" type="number" min="0" max="1000000" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $category->sort_order ?? 0) }}" required>@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="is_active">{{ __('admin.categories.status') }}</label><input type="hidden" name="is_active" value="0"><select id="is_active" name="is_active" class="form-select"><option value="1" @selected((string) old('is_active', $category->exists ? (int) $category->is_active : 1) === '1')>{{ __('admin.categories.active_badge') }}</option><option value="0" @selected((string) old('is_active', $category->exists ? (int) $category->is_active : 1) === '0')>{{ __('admin.categories.inactive_badge') }}</option></select></div>
        </div>

        <div class="row g-3">
            @foreach (['ar' => 'arabic', 'en' => 'english'] as $locale => $label)
                @php($translation = $category->translations->firstWhere('locale', $locale))
                <fieldset class="col-md-6"><legend class="h5">{{ __('admin.categories.'.$label) }}</legend>
                    <label class="form-label" for="name-{{ $locale }}">{{ __('admin.categories.name') }}</label>
                    <input id="name-{{ $locale }}" name="translations[{{ $locale }}][name]" class="form-control @error('translations.'.$locale.'.name') is-invalid @enderror" value="{{ old('translations.'.$locale.'.name', $translation?->name) }}" required maxlength="190" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                    @error('translations.'.$locale.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label mt-3" for="description-{{ $locale }}">{{ __('admin.categories.description') }}</label>
                    <textarea id="description-{{ $locale }}" name="translations[{{ $locale }}][description]" class="form-control @error('translations.'.$locale.'.description') is-invalid @enderror" rows="3" maxlength="3000" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('translations.'.$locale.'.description', $translation?->description) }}</textarea>
                    @error('translations.'.$locale.'.description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </fieldset>
            @endforeach
        </div>
        <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">{{ __('admin.categories.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.categories.save') }}</button></div>
    </form>
@endsection
