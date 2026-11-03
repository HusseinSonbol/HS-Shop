@extends('storefront.layouts.rapidshop')

@section('title', __('store.cart.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-product-breadcrumb-band">
        <div class="container hs-shell">
            <nav aria-label="{{ __('store.navigation.menu') }}">
                <ol class="hs-product-breadcrumb">
                    <li><a href="{{ route('store.home') }}">{{ __('store.navigation.home') }}</a></li>
                    <li aria-current="page">{{ __('store.cart.title') }}</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="hs-cart-page">
        <div class="container hs-shell">
            <div class="hs-cart-heading">
                <span class="hs-eyebrow">HS Shop</span>
                <h1>{{ __('store.cart.title') }}</h1>
            </div>

            @if ($items->isEmpty())
                <div class="hs-cart-empty">
                    <span class="hs-cart-empty-icon" aria-hidden="true">♡</span>
                    <p>{{ __('store.cart.empty') }}</p>
                    <a class="btn hs-button hs-button-dark" href="{{ route('store.shop') }}">{{ __('store.cart.continue_shopping') }}</a>
                </div>
            @else
                @if ($has_unavailable_items)
                    <div class="alert alert-warning" role="status">{{ __('store.cart.unavailable_items') }}</div>
                @endif
                <div class="row g-4 g-xl-5 align-items-start">
                    <div class="col-lg-8">
                        <div class="hs-cart-items">
                            @foreach ($items as $item)
                                <article class="hs-cart-item @if (! $item->storefront_is_available) is-unavailable @endif">
                                    <a class="hs-cart-item-image" href="{{ $item->variant?->product ? route('store.product', $item->variant->product->slug) : route('store.shop') }}">
                                        @if ($item->storefront_image_url)
                                            <img src="{{ $item->storefront_image_url }}" alt="{{ $item->storefront_product_name }}" loading="lazy" width="112" height="132">
                                        @else
                                            <span class="hs-image-placeholder" aria-hidden="true">HS</span>
                                        @endif
                                    </a>
                                    <div class="hs-cart-item-main">
                                        <div class="hs-cart-item-heading">
                                            <div>
                                                <h2><a href="{{ $item->variant?->product ? route('store.product', $item->variant->product->slug) : route('store.shop') }}">{{ $item->storefront_product_name }}</a></h2>
                                                @if ($item->storefront_variant_label)
                                                    <p class="hs-cart-item-variant">{{ __('store.cart.option') }}: {{ $item->storefront_variant_label }}</p>
                                                @endif
                                            </div>
                                            @if (! $item->storefront_is_available)
                                                <span class="hs-cart-unavailable-label">{{ __('store.cart.unavailable') }}</span>
                                            @endif
                                        </div>
                                        <div class="hs-cart-item-bottom">
                                            <div class="hs-cart-item-price">
                                                <span>{{ __('store.cart.unit_price') }}: {{ $item->storefront_unit_price ?? __('store.products.unavailable') }} {{ $item->storefront_unit_price ? __('store.products.currency') : '' }}</span>
                                                <strong>{{ __('store.cart.line_total') }}: {{ $item->storefront_line_total ?? '—' }} @if ($item->storefront_line_total){{ __('store.products.currency') }}@endif</strong>
                                            </div>
                                            <div class="hs-cart-item-controls">
                                                <form method="POST" action="{{ route('store.cart.items.update', $item) }}" class="hs-cart-quantity-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="visually-hidden" for="cart-quantity-{{ $item->id }}">{{ __('store.cart.quantity') }} — {{ $item->storefront_product_name }}</label>
                                                    <input id="cart-quantity-{{ $item->id }}" class="form-control" type="number" name="quantity" min="1" step="1" value="{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}" inputmode="numeric" required>
                                                    <button class="btn hs-cart-update" type="submit">{{ __('store.cart.update') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('store.cart.items.destroy', $item) }}" class="m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="hs-cart-remove" type="submit">{{ __('store.cart.remove') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                        @error('quantity')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <a class="hs-cart-continue" href="{{ route('store.shop') }}">{{ __('store.cart.continue_shopping') }} <span aria-hidden="true">↗</span></a>
                    </div>

                    <aside class="col-lg-4">
                        <div class="hs-cart-summary">
                            <h2>{{ __('store.cart.summary') }}</h2>
                            <div class="hs-cart-summary-line"><span>{{ __('store.cart.subtotal') }}</span><strong>{{ $subtotal }} {{ __('store.products.currency') }}</strong></div>
                            <div class="hs-cart-summary-line"><span>{{ __('store.cart.shipping') }}</span><span>{{ __('store.cart.shipping_calculated') }}</span></div>
                            <a class="btn hs-button hs-button-dark w-100 @if ($has_unavailable_items) disabled @endif" href="@if (! $has_unavailable_items){{ route('store.checkout') }}@else#@endif" @if ($has_unavailable_items) aria-disabled="true" tabindex="-1" @endif>{{ __('store.cart.checkout') }}</a>
                            <p class="hs-cart-summary-note">{{ __('store.cart.current_price_note') }}</p>
                        </div>
                    </aside>
                </div>
            @endif
        </div>
    </section>
@endsection
