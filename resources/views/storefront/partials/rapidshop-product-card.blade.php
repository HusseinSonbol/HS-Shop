@php
    $variant = $product->display_variant;
    $regularPrice = $variant?->display_regular_price;
    $salePrice = $variant?->display_sale_price;
    $productImage = $product->display_image_url;
    $isFavorite = (bool) ($product->is_favorite ?? false);
@endphp

<article class="hs-product-card">
    <div class="hs-product-media">
        <a class="hs-product-image" href="{{ route('store.product', $product->slug) }}" aria-label="{{ $product->display_name }}">
            @if ($productImage)
                <img src="{{ $productImage }}" alt="{{ $product->display_name }}" loading="lazy" width="480" height="600">
            @else
                <span class="hs-image-placeholder" aria-label="{{ __('store.products.no_image') }}"><span>HS</span></span>
            @endif
            @if ($salePrice !== null)
                <span class="hs-product-badge">{{ __('store.products.sale') }}</span>
            @endif
        </a>
        <div class="hs-product-actions" aria-label="{{ $product->display_name }}">
            <a class="hs-product-action" href="{{ route('store.product', $product->slug) }}" aria-label="{{ __('store.products.quick_view') }}: {{ $product->display_name }}" title="{{ __('store.products.quick_view') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.7"></circle></svg>
            </a>
            <form method="POST" action="{{ route('store.favorites.update', $product->slug) }}" class="hs-product-card-favorite-form">
                @csrf
                <input type="hidden" name="is_favorite" value="{{ $isFavorite ? '0' : '1' }}">
                <button class="hs-product-action @if ($isFavorite) is-favorite @endif" type="submit" aria-label="{{ $isFavorite ? __('store.favorites.remove') : __('store.favorites.add') }}: {{ $product->display_name }}" title="{{ $isFavorite ? __('store.favorites.remove') : __('store.favorites.add') }}" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 4.2-8.8 10-8.8 10s-8.8-5.8-8.8-10A4.6 4.6 0 0 1 12 6.3a4.6 4.6 0 0 1 8.8 2.5Z"></path></svg>
                </button>
            </form>
            <a class="hs-product-action" href="{{ route('store.product', $product->slug) }}" aria-label="{{ __('store.products.add_to_cart') }}: {{ $product->display_name }}" title="{{ __('store.products.add_to_cart') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 1.9-1.4L22 8H6"></path><circle cx="10" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
            </a>
        </div>
    </div>
    <div class="hs-product-info">
        <h3><a href="{{ route('store.product', $product->slug) }}">{{ $product->display_name }}</a></h3>
        <div class="hs-product-price">
            @if ($salePrice !== null)
                <span class="hs-current-price">{{ $salePrice }} {{ __('store.products.currency') }}</span>
                <del>{{ $regularPrice }} {{ __('store.products.currency') }}</del>
            @elseif ($regularPrice !== null)
                <span>{{ $regularPrice }} {{ __('store.products.currency') }}</span>
            @else
                <span>{{ __('store.products.unavailable') }}</span>
            @endif
        </div>
        <a class="hs-product-link" href="{{ route('store.product', $product->slug) }}">{{ __('store.products.add_to_cart') }} <span aria-hidden="true">↗</span></a>
    </div>
</article>
