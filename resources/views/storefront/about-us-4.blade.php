@extends('storefront.layouts.rapidshop')

@section('title', __('store.about_page.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-product-breadcrumb-band">
        <div class="container hs-shell">
            <nav aria-label="{{ __('store.navigation.menu') }}">
                <ol class="hs-product-breadcrumb">
                    <li><a href="{{ route('store.home') }}">{{ __('store.navigation.home') }}</a></li>
                    <li aria-current="page">{{ __('store.about_page.title') }}</li>
                </ol>
            </nav>
        </div>
    </section>

    <main class="hs-about-page">
        <div class="container hs-shell">
            <div class="row g-4 g-xl-5 align-items-start hs-about-grid">
                <section class="col-lg-6 hs-about-story" aria-labelledby="hs-about-title">
                    <div class="hs-about-story-copy">
                        <span class="hs-eyebrow">{{ __('store.about_page.eyebrow') }}</span>
                        <h1 id="hs-about-title">{{ __('store.about_page.title') }}</h1>
                    </div>
                    <figure class="hs-about-figure">
                        <img src="{{ asset('emarket/image/catalog/demo/product/funiture/9.jpg') }}" alt="{{ __('store.about_page.image_alt') }}" width="600" height="600" loading="lazy">
                        <span class="hs-about-image-mark" aria-hidden="true">HS</span>
                    </figure>
                    <div class="hs-about-description">
                        <p>{{ __('store.about_page.paragraph_one') }}</p>
                        <p>{{ __('store.about_page.paragraph_two') }}</p>
                    </div>
                </section>

                <section class="col-lg-6 hs-about-faqs" aria-labelledby="hs-about-faq-title">
                    <div class="hs-about-faq-heading">
                        <span class="hs-eyebrow">{{ __('store.about_page.faq_eyebrow') }}</span>
                        <h2 id="hs-about-faq-title">{{ __('store.about_page.faq_title') }}</h2>
                    </div>

                    @if ($faqs->isNotEmpty())
                        <div class="accordion hs-about-accordion" id="hsAboutFaqAccordion">
                            @foreach ($faqs as $faq)
                                @php($panelId = 'hs-about-faq-'.$faq->id)
                                <article class="accordion-item hs-about-faq-item">
                                    <h3 class="accordion-header" id="{{ $panelId }}-heading">
                                        <button class="accordion-button @if (! $loop->first) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $panelId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $panelId }}">
                                            <span>{{ $faq->display_question }}</span>
                                        </button>
                                    </h3>
                                    <div id="{{ $panelId }}" class="accordion-collapse collapse @if ($loop->first) show @endif" aria-labelledby="{{ $panelId }}-heading" data-bs-parent="#hsAboutFaqAccordion">
                                        <div class="accordion-body">{{ $faq->display_answer }}</div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="hs-about-faq-empty">
                            <span aria-hidden="true">?</span>
                            <p>{{ __('store.about_page.faq_empty') }}</p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </main>
@endsection
