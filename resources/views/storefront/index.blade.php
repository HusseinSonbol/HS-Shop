@extends('storefront.layouts.rapidshop')

@section('title', __('store.brand').' | '.__('store.navigation.home'))

@section('content')
    @if ($heroEnabled)
        @php($heroBannerList = $banners->get('home_hero', collect()))
        <section class="hs-hero" aria-label="{{ __('store.hero.title') }}">
            @if ($heroBannerList->isNotEmpty())
                <div id="hsHomeHero" class="carousel slide hs-hero-carousel" data-bs-ride="carousel" data-bs-interval="6000">
                    <div class="carousel-indicators">
                        @foreach ($heroBannerList as $index => $banner)
                            <button type="button" data-bs-target="#hsHomeHero" data-bs-slide-to="{{ $index }}" @if ($index === 0) class="active" aria-current="true" @endif aria-label="{{ __('store.hero.eyebrow') }} {{ $index + 1 }}"></button>
                        @endforeach
                    </div>
                    <div class="carousel-inner">
                        @foreach ($heroBannerList as $index => $banner)
                            <div class="carousel-item @if ($index === 0) active @endif">
                                <div class="hs-hero-slide" style="--hero-image: url('{{ $banner->display_image_url }}')" role="img" aria-label="{{ $banner->display_alt ?: $banner->display_title ?: __('store.hero.eyebrow') }}">
                                    <div class="container hs-shell">
                                        <div class="hs-hero-copy">
                                            <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.hero.eyebrow') }}</span>
                                            <h1>{{ $banner->display_title ?: __('store.hero.title') }}</h1>
                                            <p>{{ __('store.hero.description') }}</p>
                                            <div class="hs-hero-actions">
                                                <a class="btn hs-button hs-button-gold" href="{{ $banner->target_url ?: route('store.shop') }}">{{ __('store.hero.cta') }} <span aria-hidden="true">↗</span></a>
                                                <a class="hs-text-link" href="{{ route('store.about') }}">{{ __('store.hero.secondary') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($heroBannerList->count() > 1)
                        <button class="carousel-control-prev hs-carousel-control" type="button" data-bs-target="#hsHomeHero" data-bs-slide="prev" aria-label="Previous slide"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                        <button class="carousel-control-next hs-carousel-control" type="button" data-bs-target="#hsHomeHero" data-bs-slide="next" aria-label="Next slide"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                    @endif
                </div>
            @else
                <div class="hs-hero-fallback">
                    <div class="hs-hero-orbit hs-hero-orbit-one"></div>
                    <div class="hs-hero-orbit hs-hero-orbit-two"></div>
                    <div class="container hs-shell hs-hero-fallback-content">
                        <div class="hs-hero-copy">
                            <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.hero.eyebrow') }}</span>
                            <h1>{!! nl2br(e(__('store.hero.title'))) !!}</h1>
                            <p>{{ __('store.hero.description') }}</p>
                            <div class="hs-hero-actions">
                                <a class="btn hs-button hs-button-gold" href="{{ route('store.shop') }}">{{ __('store.hero.cta') }} <span aria-hidden="true">↗</span></a>
                                <a class="hs-text-link" href="{{ route('store.about') }}">{{ __('store.hero.secondary') }}</a>
                            </div>
                        </div>
                        <div class="hs-hero-mark" aria-hidden="true"><span>HS</span><i></i><b>SHOP</b></div>
                        <div class="hs-hero-caption"><span class="hs-caption-line"></span>{{ __('store.hero.badge') }}</div>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section class="hs-category-section hs-section" id="category-list">
            <div class="container hs-shell">
                <div class="hs-section-heading text-center">
                    <span class="hs-eyebrow">{{ __('store.categories.eyebrow') }}</span>
                    <h2>{{ __('store.categories.title') }}</h2>
                    <span class="hs-heading-rule" aria-hidden="true"></span>
                </div>
                <div class="row g-3 g-lg-4 justify-content-center">
                    @foreach ($categories as $category)
                        <div class="col-6 col-md-4 col-lg-4">
                            <a class="hs-category-card" href="{{ route('store.category', $category->slug) }}" aria-label="{{ __('store.categories.shop_category') }}: {{ $category->display_name }}">
                                <span class="hs-category-image">
                                    @if ($category->display_image_url)
                                        <img src="{{ $category->display_image_url }}" alt="{{ $category->display_name }}" loading="lazy" width="440" height="520">
                                    @else
                                        <span class="hs-category-image-fallback" aria-hidden="true">HS</span>
                                    @endif
                                </span>
                                <span class="hs-category-overlay">
                                    <span class="hs-category-label">HS Shop</span>
                                    <strong>{{ $category->display_name }}</strong>
                                    <span class="hs-category-description">{{ $category->display_description }}</span>
                                    <span class="hs-category-cta">{{ __('store.categories.shop_category') }} <span aria-hidden="true">↗</span></span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @foreach ($orderedSections as $section)
        @continue($section->code === 'home_hero')

        @if ($section->section_type === 'product_collection')
            @php($sectionProducts = $productsByCollection->get($section->product_collection_id, collect()))
            @if ($sectionProducts->isNotEmpty())
                @php($sectionHeading = $section->translations->firstWhere('locale', $locale)?->heading ?? $section->translations->firstWhere('locale', 'en')?->heading)
                @php($fallbackHeading = $section->collection?->code === 'popular' ? __('store.products.popular') : __('store.products.new'))
                <section class="hs-product-section hs-section {{ $section->collection?->code === 'popular' ? 'hs-product-section--popular' : 'hs-product-section--new' }}">
                    <div class="container hs-shell">
                        <div class="hs-section-heading hs-section-heading-row">
                            <div>
                                <span class="hs-eyebrow">{{ __('store.products.eyebrow') }}</span>
                                <h2>{{ $sectionHeading ?: $fallbackHeading }}</h2>
                            </div>
                            <div class="hs-product-section-actions">
                                <div class="hs-product-carousel-controls" data-carousel-controls hidden>
                                    <button class="hs-product-carousel-button" type="button" data-carousel-prev aria-label="{{ __('store.products.previous') }}">@if ($isRtl) › @else ‹ @endif</button>
                                    <button class="hs-product-carousel-button" type="button" data-carousel-next aria-label="{{ __('store.products.next') }}">@if ($isRtl) ‹ @else › @endif</button>
                                </div>
                                <a class="hs-view-all" href="{{ route('store.shop') }}">{{ __('store.products.view_all') }} <span aria-hidden="true">↗</span></a>
                            </div>
                        </div>
                        <div class="hs-product-carousel" data-product-carousel data-interval="7000">
                            <div class="hs-product-carousel-viewport" data-carousel-viewport dir="ltr">
                                <div class="hs-product-carousel-track" data-carousel-track dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                                    @foreach ($sectionProducts as $product)
                                        <div class="hs-product-carousel-slide" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                                            @include('storefront.partials.rapidshop-product-card', ['product' => $product])
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        @elseif ($section->section_type === 'banner_placement' && $section->banner_placement === 'home_promo_top' && $banners->get('home_promo_top', collect())->isNotEmpty())
            @php($promoBanner = $banners->get('home_promo_top')->first())
            <section class="hs-promo-section container hs-shell" aria-label="{{ __('store.offers.title') }}">
                <a class="hs-promo-feature" href="{{ $promoBanner->target_url ?: route('store.shop') }}">
                    <img src="{{ $promoBanner->display_image_url }}" alt="{{ $promoBanner->display_alt ?: $promoBanner->display_title ?: __('store.offers.title') }}" loading="lazy" width="1320" height="500">
                    <span class="hs-promo-feature-shade" aria-hidden="true"></span>
                    <span class="hs-promo-feature-content">
                        <span class="hs-promo-kicker">{{ __('store.offers.eyebrow') }}</span>
                        <strong>{{ $promoBanner->display_title ?: __('store.offers.title') }}</strong>
                        <span class="hs-promo-feature-description">{{ __('store.offers.description') }}</span>
                        <span class="hs-button hs-button-gold">{{ __('store.offers.cta') }} <span aria-hidden="true">↗</span></span>
                    </span>
                </a>
            </section>
        @elseif ($section->section_type === 'banner_placement' && $section->banner_placement === 'home_promo_middle' && $banners->get('home_promo_middle', collect())->isNotEmpty())
            <section class="hs-feature-offer">
                <div class="container hs-shell">
                    <div class="row g-3 g-lg-4">
                        @foreach ($banners->get('home_promo_middle') as $banner)
                            <div class="col-md-6">
                                <a class="hs-feature-card" href="{{ $banner->target_url ?: route('store.shop') }}" style="--promo-image: url('{{ $banner->display_image_url }}')">
                                    <span class="hs-feature-copy">
                                        <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.offers.eyebrow') }}</span>
                                        <strong>{{ $banner->display_title ?: __('store.offers.title') }}</strong>
                                        <span class="hs-button hs-button-gold">{{ __('store.offers.cta') }} <span aria-hidden="true">↗</span></span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @elseif ($section->section_type === 'testimonials')
            @php($brandNames = [
                'lc-waikiki' => 'LC Waikiki',
                'defacto' => 'DeFacto',
                'town-team' => 'Town Team',
                'sutra' => 'Sutra',
                'malameh' => 'Malameh',
                'u-turn' => 'U-Turn',
                'zara' => 'ZARA',
                'tie-house' => 'Tie House',
            ])
            <section class="hs-brand-strip" aria-label="{{ __('store.brands.title') }}">
                <div class="container hs-shell">
                    <div class="hs-brand-strip-heading">
                        <span class="hs-eyebrow">{{ __('store.brands.eyebrow') }}</span>
                        <h2>{{ __('store.brands.title') }}</h2>
                    </div>
                </div>
                <div class="hs-brand-marquee" dir="ltr">
                    <div class="hs-brand-marquee-track">
                        @foreach ([0, 1] as $copy)
                            <div class="hs-brand-marquee-group" @if ($copy === 1) aria-hidden="true" @endif>
                                @foreach ($brandNames as $brandClass => $brandName)
                                    <span class="hs-brand-pill hs-brand-pill--{{ $brandClass }}"><span>{{ $brandName }}</span></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            @if ($testimonials->isNotEmpty())
            @php($sectionHeading = $section->translations->firstWhere('locale', $locale)?->heading ?? $section->translations->firstWhere('locale', 'en')?->heading)
            <section class="hs-testimonial-band" aria-label="{{ __('store.testimonials.title') }}">
                <div class="hs-testimonial-backdrop" aria-hidden="true"></div>
                <div class="container hs-shell hs-testimonial-content">
                    <div class="hs-section-heading text-center">
                        <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.testimonials.eyebrow') }}</span>
                        <h2>{{ $sectionHeading ?: __('store.testimonials.title') }}</h2>
                        <span class="hs-heading-rule" aria-hidden="true"></span>
                    </div>
                    <div id="hsTestimonials" class="carousel slide hs-testimonial-carousel" data-bs-ride="carousel" data-bs-interval="5000">
                        <div class="carousel-inner">
                            @foreach ($testimonials as $index => $testimonial)
                                <div class="carousel-item @if ($index === 0) active @endif">
                                    <figure class="hs-testimonial-slide text-center mb-0">
                                        <img class="hs-testimonial-photo" src="{{ $testimonial['display_image_url'] }}" alt="{{ $testimonial['display_name'] }}" loading="lazy" width="100" height="100">
                                        <blockquote class="hs-testimonial-message">“{{ $testimonial['display_message'] }}”</blockquote>
                                        <figcaption class="hs-testimonial-name">{{ $testimonial['display_name'] }}</figcaption>
                                    </figure>
                                </div>
                            @endforeach
                        </div>
                        @if ($testimonials->count() > 1)
                            <button class="carousel-control-prev hs-testimonial-control" type="button" data-bs-target="#hsTestimonials" data-bs-slide="prev" aria-label="{{ __('store.testimonials.previous') }}"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                            <button class="carousel-control-next hs-testimonial-control" type="button" data-bs-target="#hsTestimonials" data-bs-slide="next" aria-label="{{ __('store.testimonials.next') }}"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                            <div class="carousel-indicators">
                                @foreach ($testimonials as $index => $testimonial)
                                    <button type="button" data-bs-target="#hsTestimonials" data-bs-slide-to="{{ $index }}" @if ($index === 0) class="active" aria-current="true" @endif aria-label="{{ __('store.testimonials.slide') }} {{ $index + 1 }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
            @endif
        @endif
    @endforeach

    @if ($reviews->isNotEmpty() || $feedback->isNotEmpty())
        <section class="hs-review-section hs-section">
            <div class="container hs-shell">
                <div class="hs-section-heading text-center">
                    <span class="hs-eyebrow">{{ __('store.reviews.eyebrow') }}</span>
                    <h2>{{ __('store.reviews.title') }}</h2>
                    <span class="hs-heading-rule" aria-hidden="true"></span>
                </div>
                <div class="row g-3 g-lg-4">
                    @foreach ($reviews as $review)
                        @php($reviewProduct = $review->product->translations->firstWhere('locale', $locale)?->name ?? $review->product->translations->firstWhere('locale', 'en')?->name)
                        <div class="col-md-6 col-lg-4">
                            <article class="hs-review-card">
                                <span class="hs-review-quote" aria-hidden="true">“</span>
                                <p>“{{ $review->comment }}”</p>
                                <span class="hs-review-meta">{{ $reviewProduct ?: __('store.reviews.product_review') }}</span>
                            </article>
                        </div>
                    @endforeach
                    @foreach ($feedback as $item)
                        <div class="col-md-6 col-lg-4">
                            <article class="hs-review-card hs-feedback-card">
                                <span class="hs-review-quote" aria-hidden="true">“</span>
                                <p>“{{ $item->feedback_text }}”</p>
                                <span class="hs-review-meta">{{ __('store.reviews.eyebrow') }}</span>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="hs-benefits" aria-label="{{ __('store.benefits.support_title') }}">
        <div class="container hs-shell">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="hs-benefit-item"><span class="hs-benefit-icon" aria-hidden="true">↗</span><div><h2>{{ __('store.benefits.shipping_title') }}</h2><p>{{ __('store.benefits.shipping_text') }}</p></div></div>
                </div>
                <div class="col-md-4">
                    <div class="hs-benefit-item"><span class="hs-benefit-icon" aria-hidden="true">✓</span><div><h2>{{ __('store.benefits.payment_title') }}</h2><p>{{ __('store.benefits.payment_text') }}</p></div></div>
                </div>
                <div class="col-md-4">
                    <div class="hs-benefit-item"><span class="hs-benefit-icon" aria-hidden="true">♡</span><div><h2>{{ __('store.benefits.support_title') }}</h2><p>{{ __('store.benefits.support_text') }}</p></div></div>
                </div>
            </div>
        </div>
    </section>

    @include('storefront.partials.contact-section')

@endsection
