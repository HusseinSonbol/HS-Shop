@extends('storefront.layouts.rapidshop')

@section('title', __('store.checkout.order_confirmation').' | '.__('store.brand'))

@section('content')
    <section class="hs-cart-page hs-order-confirmation-page">
        <div class="container hs-shell">
            <div class="hs-order-confirmation-card">
                <span class="hs-order-confirmation-mark" aria-hidden="true">✓</span>
                <span class="hs-eyebrow">{{ __('store.checkout.order_confirmation') }}</span>
                <h1>{{ __('store.checkout.order_placed') }}</h1>
                <p>{{ __('store.checkout.order_number') }} <strong>{{ $order->order_number }}</strong></p>
                <p class="text-secondary">{{ __('store.checkout.cod_only_note') }}</p>

                <div class="hs-order-confirmation-lines">
                    @foreach ($items as $item)
                        @php($variant = json_decode($item->variant_snapshot ?? '[]', true) ?: [])
                        <div class="hs-cart-summary-line">
                            <span>{{ $item->product_name_snapshot }}<small class="d-block text-secondary">{{ $variant['name'] ?? $item->sku_snapshot }} · {{ __('store.cart.quantity') }}: {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</small></span>
                            <strong>{{ $item->line_total }} {{ __('store.products.currency') }}</strong>
                        </div>
                    @endforeach
                    <div class="hs-cart-summary-line"><span>{{ __('store.cart.subtotal') }}</span><strong>{{ $order->subtotal }} {{ __('store.products.currency') }}</strong></div>
                    <div class="hs-cart-summary-line"><span>{{ __('store.cart.shipping') }}</span><strong>{{ $order->customer_shipping_charge }} {{ __('store.products.currency') }}</strong></div>
                    <div class="hs-cart-summary-line hs-order-confirmation-total"><span>{{ __('store.checkout.grand_total') }}</span><strong>{{ $order->grand_total }} {{ __('store.products.currency') }}</strong></div>
                </div>

                <div class="hs-order-confirmation-address">
                    <strong>{{ __('store.checkout.delivery_address') }}</strong>
                    <span>{{ $order->recipient_name }} · <bdi>{{ $order->phone }}</bdi></span>
                    <span>{{ collect([$order->street, $order->building, $order->city, $order->governorate])->filter()->join('، ') }}</span>
                </div>

                <a class="btn hs-button hs-button-dark mt-4" href="{{ route('store.shop') }}">{{ __('store.cart.continue_shopping') }}</a>
            </div>
        </div>
    </section>
@endsection
