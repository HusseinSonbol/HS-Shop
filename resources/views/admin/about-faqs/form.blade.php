@extends('admin.layouts.app')

@section('title', $faq->exists ? __('admin.about_faqs.edit') : __('admin.about_faqs.add'))

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ __('admin.about_faqs.title') }}</p><h1>{{ $faq->exists ? __('admin.about_faqs.edit') : __('admin.about_faqs.add') }}</h1></div></section>
    <section class="card panel customer-list-panel"><form method="POST" action="{{ $faq->exists ? route('admin.about-faqs.update', $faq) : route('admin.about-faqs.store') }}" class="p-3 p-md-4">
        @csrf @if($faq->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="sort_order">{{ __('admin.about_faqs.sort_order') }}</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" class="form-control" value="{{ old('sort_order', $faq->sort_order ?? 0) }}" required>@error('sort_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-6 d-flex align-items-end"><input type="hidden" name="is_active" value="0"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $faq->is_active ?? true))><span class="form-check-label">{{ __('admin.about_faqs.visible') }}</span></label></div>
            @foreach(['ar', 'en'] as $locale)
                @php($translation = $faq->translations->firstWhere('locale', $locale))
                <div class="col-12"><h2 class="h5 mb-0">{{ __('admin.homepage.locales.'.$locale) }}</h2></div>
                <div class="col-12"><label class="form-label" for="question-{{ $locale }}">{{ __('admin.about_faqs.question') }}</label><input id="question-{{ $locale }}" name="translations[{{ $locale }}][question]" maxlength="500" class="form-control" value="{{ old('translations.'.$locale.'.question', $translation?->question) }}" required dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">@error('translations.'.$locale.'.question')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="answer-{{ $locale }}">{{ __('admin.about_faqs.answer') }}</label><textarea id="answer-{{ $locale }}" name="translations[{{ $locale }}][answer]" rows="5" maxlength="10000" class="form-control" required dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('translations.'.$locale.'.answer', $translation?->answer) }}</textarea>@error('translations.'.$locale.'.answer')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            @endforeach
            <div class="col-12 form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.about-faqs.index') }}">{{ __('admin.about_faqs.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.about_faqs.save') }}</button></div>
        </div>
    </form></section>
@endsection
