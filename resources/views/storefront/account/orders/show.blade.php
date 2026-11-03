@extends('storefront.layouts.rapidshop')

@section('title', __('store.customer_orders.order_details').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page hs-customer-orders-page">
        <div class="container hs-shell">
            <div class="hs-account-heading mb-4">
                <span class="hs-eyebrow">{{ __('store.customer_orders.title') }}</span>
                <h1>{{ __('store.customer_orders.order_details') }}</h1>
                <p>{{ __('store.customer_orders.order_number') }} <strong>{{ $order->order_number }}</strong></p>
                <a class="hs-customer-orders-back" href="{{ route('store.account.orders.index') }}">{{ __('store.customer_orders.back_to_orders') }}</a>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <section class="hs-account-panel hs-customer-order-details-panel">
                        <div class="hs-customer-order-main mb-4">
                            <div>
                                <span class="hs-customer-order-label">{{ __('store.customer_orders.placed_at') }}</span>
                                <p class="mb-0">{{ \Illuminate\Support\Carbon::parse($order->placed_at)->locale($locale)->translatedFormat('d M Y · h:i A') }}</p>
                            </div>
                            <span class="hs-customer-order-status">{{ $order->status_label }}</span>
                        </div>

                        @if ($items->isEmpty())
                            <div class="hs-account-empty">{{ __('store.customer_orders.items_unavailable') }}</div>
                        @else
                            <div class="hs-customer-order-lines">
                                @foreach ($items as $item)
                                    @php($variant = json_decode($item->variant_snapshot ?? '[]', true) ?: [])
                                    <article class="hs-customer-order-line">
                                        @if ($item->product_image_path_snapshot)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->product_image_path_snapshot) }}" alt="{{ $item->product_name_snapshot }}" loading="lazy" width="82" height="96">
                                        @else
                                            <span class="hs-image-placeholder" aria-hidden="true">HS</span>
                                        @endif
                                        <div class="hs-customer-order-line-copy">
                                            <h2>{{ $item->product_name_snapshot }}</h2>
                                            <p>{{ __('store.customer_orders.sku') }}: <bdi>{{ $item->sku_snapshot }}</bdi></p>
                                            @if (! empty($variant['name']))<p>{{ __('store.customer_orders.variant') }}: {{ $variant['name'] }}</p>@endif
                                            <p>{{ __('store.cart.quantity') }}: {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</p>
                                        </div>
                                        <div class="hs-customer-order-line-price">
                                            @if ($item->sale_unit_price_snapshot !== null)
                                                <del>{{ $item->regular_unit_price_snapshot }} {{ __('store.products.currency') }}</del>
                                            @endif
                                            <strong>{{ $item->charged_unit_price }} {{ __('store.products.currency') }}</strong>
                                            <span>{{ __('store.customer_orders.line_total') }}: {{ $item->line_total }} {{ __('store.products.currency') }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="col-lg-4">
                    <section class="hs-account-panel hs-customer-order-summary">
                        <h2>{{ __('store.cart.summary') }}</h2>
                        <div class="hs-cart-summary-line"><span>{{ __('store.cart.subtotal') }}</span><strong>{{ $order->subtotal ?? '0.0000' }} {{ __('store.products.currency') }}</strong></div>
                        <div class="hs-cart-summary-line"><span>{{ __('store.customer_orders.discount') }}</span><strong>{{ $order->discount_total ?? '0.0000' }} {{ __('store.products.currency') }}</strong></div>
                        <div class="hs-cart-summary-line"><span>{{ __('store.cart.shipping') }}</span><strong>{{ $order->customer_shipping_charge ?? '0.0000' }} {{ __('store.products.currency') }}</strong></div>
                        @if ($order->tax_total !== null)
                            <div class="hs-cart-summary-line"><span>{{ __('store.customer_orders.tax') }}</span><strong>{{ $order->tax_total }} {{ __('store.products.currency') }}</strong></div>
                        @endif
                        <div class="hs-cart-summary-line hs-order-confirmation-total"><span>{{ __('store.checkout.grand_total') }}</span><strong>{{ $order->grand_total ?? $order->order_grand_total }} {{ __('store.products.currency') }}</strong></div>
                        <div class="hs-customer-order-delivery">
                            <h3>{{ __('store.checkout.delivery_address') }}</h3>
                            @if ($order->recipient_name)
                                <p><strong>{{ $order->recipient_name }}</strong> · <bdi>{{ $order->phone }}</bdi></p>
                                <p>{{ collect([$order->street, $order->building, $order->floor, $order->apartment, $order->district, $order->city, $order->governorate])->filter()->join('، ') }}</p>
                                @if ($order->landmark)<p>{{ __('store.customer_account.landmark') }}: {{ $order->landmark }}</p>@endif
                            @else
                                <p>{{ __('store.customer_orders.address_unavailable') }}</p>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </section>
@endsection
