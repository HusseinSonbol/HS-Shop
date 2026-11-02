@extends('storefront.layouts.rapidshop')

@section('title', $product->display_name.' | '.__('store.brand'))

@section('content')
    @php
        $selectedVariant = $product->display_variant;
        $productImages = $product->display_images;
        $mainImage = $productImages->first();
    @endphp

    <section class="hs-product-breadcrumb-band">
        <div class="container hs-shell">
            <nav aria-label="{{ __('store.navigation.menu') }}">
                <ol class="hs-product-breadcrumb">
                    <li><a href="{{ route('store.home') }}">{{ __('store.product_detail.breadcrumb_home') }}</a></li>
                    <li><a href="{{ route('store.shop') }}">{{ __('store.product_detail.breadcrumb_shop') }}</a></li>
                    @if ($categoryName)
                        <li><span>{{ $categoryName }}</span></li>
                    @endif
                    <li aria-current="page"><span>{{ $product->display_name }}</span></li>
                </ol>
            </nav>
        </div>
    </section>

    <div class="hs-product-detail-page" data-product-detail>
        <div class="container hs-shell">
            @if (session('cart_status'))
                <div class="alert alert-success hs-product-cart-alert" role="status">{{ session('cart_status') }}</div>
            @endif
            @if (session('favorite_status'))
                <div class="alert alert-success hs-product-cart-alert" role="status">{{ session('favorite_status') }}</div>
            @endif
            @if (session('account_status'))
                <div class="alert alert-success hs-product-cart-alert" role="status">{{ session('account_status') }}</div>
            @endif

            <section class="row g-4 g-xl-5 hs-product-detail" aria-label="{{ $product->display_name }}">
                <div class="col-lg-6">
                    <div class="hs-product-gallery">
                        <div class="hs-product-gallery-main" data-product-gallery-main>
                            @if ($mainImage)
                                <img src="{{ $mainImage['url'] }}" alt="{{ $mainImage['alt'] }}" data-main-product-image data-product-name="{{ $product->display_name }}" fetchpriority="high" width="720" height="820">
                            @else
                                <div class="hs-image-placeholder hs-product-gallery-placeholder" aria-label="{{ __('store.products.no_image') }}"><span>HS</span></div>
                            @endif
                            <span class="hs-product-detail-badge" data-product-sale-badge @if (! ($selectedVariant?->current_sale_price !== null && $selectedVariant->current_sale_price < $selectedVariant->current_regular_price)) hidden @endif>{{ __('store.products.sale') }}</span>
                        </div>
                        @if ($productImages->count() > 1)
                            <div class="hs-product-gallery-thumbnails" aria-label="{{ __('store.product_detail.details') }}">
                                @foreach ($productImages as $index => $image)
                                    <button type="button" class="hs-product-gallery-thumb @if ($index === 0) is-active @endif" data-gallery-thumb data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] }}" aria-label="{{ __('store.product_detail.image', ['number' => $index + 1]) }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                        <img src="{{ $image['url'] }}" alt="" loading="lazy" width="100" height="115">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hs-product-detail-info">
                        @if ($brandName)
                            <span class="hs-product-detail-brand">{{ $brandName }}</span>
                        @endif
                        <h1>{{ $product->display_name }}</h1>
                        <div class="hs-product-detail-meta">
                            <span>{{ __('store.product_detail.sku') }}: <b>{{ $product->sku }}</b></span>
                            <span class="hs-product-availability"><i aria-hidden="true"></i>{{ __('store.product_detail.available') }}</span>
                        </div>

                        <div class="hs-product-detail-price" aria-live="polite" data-product-price>
                            @if ($selectedVariant?->current_sale_price !== null && $selectedVariant->current_sale_price < $selectedVariant->current_regular_price)
                                <strong data-sale-price data-currency-label="{{ __('store.products.currency') }}"><span data-price-value>{{ $selectedVariant->display_sale_price }}</span> <small>{{ __('store.products.currency') }}</small></strong>
                                <del data-regular-price data-currency-label="{{ __('store.products.currency') }}">{{ $selectedVariant->display_regular_price }} {{ __('store.products.currency') }}</del>
                            @elseif ($selectedVariant)
                                <strong data-sale-price data-currency-label="{{ __('store.products.currency') }}"><span data-price-value>{{ $selectedVariant->display_regular_price }}</span> <small>{{ __('store.products.currency') }}</small></strong>
                                <del data-regular-price data-currency-label="{{ __('store.products.currency') }}" hidden></del>
                            @endif
                        </div>

                        @if ($product->display_short_description)
                            <p class="hs-product-detail-summary">{{ $product->display_short_description }}</p>
                        @endif

                        <form method="POST" action="{{ route('store.cart.items.store') }}" class="hs-product-purchase-form">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $selectedVariant?->id }}" data-selected-variant>
                            @error('variant_id')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                            @foreach ($attributeGroups as $group)
                                <fieldset class="hs-product-option-group" data-attribute-group="{{ $group['id'] }}">
                                    <legend>{{ __('store.product_detail.choose_option', ['name' => $group['name']]) }}</legend>
                                    <div class="hs-product-option-list">
                                        @foreach ($group['values'] as $value)
                                            <button type="button" class="hs-product-option @if (($selectedVariant->display_attributes[$group['id']]['value_id'] ?? null) === $value['value_id']) is-selected @endif" data-attribute-id="{{ $group['id'] }}" data-value-id="{{ $value['value_id'] }}" aria-pressed="{{ ($selectedVariant->display_attributes[$group['id']]['value_id'] ?? null) === $value['value_id'] ? 'true' : 'false' }}">{{ $value['value'] }}</button>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach

                            @if ($attributeGroups->isEmpty() && $product->variants->count() > 1)
                                <label class="hs-product-option-group d-block" for="product-variant-select">
                                    <span class="hs-product-option-legend">{{ __('store.product_detail.choose_option', ['name' => __('store.product_detail.variant')]) }}</span>
                                    <select id="product-variant-select" class="form-select hs-product-variant-select" data-direct-variant>
                                        @foreach ($product->variants as $variant)
                                            <option value="{{ $variant->id }}" @selected($variant->id === $selectedVariant->id)>{{ $variant->display_label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @error('quantity')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                            <div class="hs-product-purchase-actions">
                                <label class="hs-product-quantity" for="product-quantity">
                                    <span>{{ __('store.product_detail.quantity') }}</span>
                                    <input id="product-quantity" type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity', '1') }}" inputmode="decimal" required>
                                </label>
                                <button class="btn hs-button hs-button-dark hs-product-add-button" type="submit" @disabled(! $selectedVariant)>
                                    {{ __('store.product_detail.add_to_cart') }}
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 1.9-1.4L22 8H6"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('store.favorites.update', $product->slug) }}" class="hs-product-favorite-form">
                            @csrf
                            <input type="hidden" name="is_favorite" value="{{ $isFavorite ? '0' : '1' }}">
                            <button class="hs-product-favorite-button @if ($isFavorite) is-favorite @endif" type="submit" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 4.2-8.8 10-8.8 10s-8.8-5.8-8.8-10A4.6 4.6 0 0 1 12 6.3a4.6 4.6 0 0 1 8.8 2.5Z"></path></svg>
                                {{ $isFavorite ? __('store.favorites.remove') : __('store.favorites.add') }}
                            </button>
                        </form>

                        <div class="hs-product-detail-assurance">
                            <span><i aria-hidden="true">✓</i>{{ __('store.benefits.shipping_title') }}</span>
                            <span><i aria-hidden="true">✓</i>{{ __('store.benefits.payment_title') }}</span>
                        </div>

                        <div class="hs-product-share" data-product-share data-share-title="{{ $product->display_name }}" data-share-url="{{ route('store.product', $product->slug) }}" data-copy-success="{{ __('store.product_detail.link_copied') }}" data-copy-failure="{{ __('store.product_detail.link_copy_failed') }}">
                            <span class="hs-product-share-title">{{ __('store.product_detail.share') }}</span>
                            <div class="hs-product-share-actions">
                                <a class="hs-social-share hs-social-share--whatsapp" href="https://wa.me/?text={{ rawurlencode($product->display_name.' '.route('store.product', $product->slug)) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('store.product_detail.share_whatsapp') }}">WA</a>
                                <a class="hs-social-share" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(route('store.product', $product->slug)) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('store.product_detail.share_facebook') }}">f</a>
                                <a class="hs-social-share" href="https://twitter.com/intent/tweet?url={{ rawurlencode(route('store.product', $product->slug)) }}&amp;text={{ rawurlencode($product->display_name) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('store.product_detail.share_x') }}">𝕏</a>
                                <a class="hs-social-share" href="https://t.me/share/url?url={{ rawurlencode(route('store.product', $product->slug)) }}&amp;text={{ rawurlencode($product->display_name) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('store.product_detail.share_telegram') }}">➤</a>
                                <button class="hs-social-share hs-social-share-copy" type="button" data-share-copy aria-label="{{ __('store.product_detail.copy_link') }}" title="{{ __('store.product_detail.copy_link') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                                </button>
                                <button class="hs-social-share hs-social-share-native" type="button" data-share-native aria-label="{{ __('store.product_detail.share') }}" title="{{ __('store.product_detail.share') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.7 10.7 6.6-4.4M8.7 13.3l6.6 4.4"></path></svg>
                                </button>
                            </div>
                            <p class="hs-product-share-status" data-share-status role="status" aria-live="polite" hidden></p>
                        </div>
                    </div>
                </div>
            </section>

            <script type="application/json" data-product-variants>@json($variantOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>

            <section class="hs-product-detail-tabs" aria-label="{{ __('store.product_detail.details') }}">
                <ul class="nav hs-product-detail-tab-list" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" id="product-overview-tab" data-bs-toggle="tab" data-bs-target="#product-overview" type="button" role="tab" aria-controls="product-overview" aria-selected="true">{{ __('store.product_detail.overview') }}</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="product-information-tab" data-bs-toggle="tab" data-bs-target="#product-information" type="button" role="tab" aria-controls="product-information" aria-selected="false">{{ __('store.product_detail.information') }}</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="product-reviews-tab" data-bs-toggle="tab" data-bs-target="#product-reviews" type="button" role="tab" aria-controls="product-reviews" aria-selected="false">{{ __('store.product_detail.reviews') }} <span>{{ $reviews->count() }}</span></button></li>
                </ul>
                <div class="tab-content hs-product-detail-tab-content">
                    <div class="tab-pane fade show active" id="product-overview" role="tabpanel" aria-labelledby="product-overview-tab" tabindex="0">
                        @if ($product->display_description)
                            <div class="hs-product-description">{!! nl2br(e($product->display_description)) !!}</div>
                        @else
                            <p class="mb-0">{{ __('store.product_detail.no_description') }}</p>
                        @endif
                    </div>
                    <div class="tab-pane fade" id="product-information" role="tabpanel" aria-labelledby="product-information-tab" tabindex="0">
                        <dl class="hs-product-information-list">
                            <div><dt>{{ __('store.product_detail.sku') }}</dt><dd>{{ $product->sku }}</dd></div>
                            @if ($brandName)<div><dt>{{ __('store.product_detail.brand') }}</dt><dd>{{ $brandName }}</dd></div>@endif
                            @if ($categoryName)<div><dt>{{ __('store.product_detail.category') }}</dt><dd>{{ $categoryName }}</dd></div>@endif
                            <div><dt>{{ __('store.product_detail.variant_code') }}</dt><dd data-variant-sku>{{ $selectedVariant?->sku }}</dd></div>
                        </dl>
                    </div>
                    <div class="tab-pane fade" id="product-reviews" role="tabpanel" aria-labelledby="product-reviews-tab" tabindex="0">
                        @forelse ($reviews as $review)
                            <article class="hs-product-review">
                                <span class="hs-product-review-mark" aria-hidden="true">HS</span>
                                <div><strong>{{ __('store.reviews.product_review') }}</strong><p>{{ $review->comment }}</p></div>
                            </article>
                        @empty
                            <p class="mb-0">{{ __('store.product_detail.no_reviews') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>

            @if ($relatedProducts->isNotEmpty())
                <section class="hs-related-products hs-section" aria-labelledby="hs-related-products-title">
                    <div class="hs-section-heading hs-section-heading-row">
                        <div><span class="hs-eyebrow">{{ __('store.products.eyebrow') }}</span><h2 id="hs-related-products-title">{{ __('store.product_detail.related') }}</h2></div>
                        <a class="hs-view-all" href="{{ route('store.shop') }}">{{ __('store.products.view_all') }} <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="row g-3 g-lg-4">
                        @foreach ($relatedProducts as $relatedProduct)
                            <div class="col-6 col-md-4 col-lg-3">@include('storefront.partials.rapidshop-product-card', ['product' => $relatedProduct])</div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
