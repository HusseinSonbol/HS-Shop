@extends('storefront.layouts.rapidshop')

@section('title', __('store.customer_orders.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page hs-customer-orders-page">
        <div class="container hs-shell">
            <div class="hs-account-heading mb-4">
                <span class="hs-eyebrow">{{ __('store.customer_account.title') }}</span>
                <h1>{{ __('store.customer_orders.title') }}</h1>
                <p>{{ __('store.customer_orders.description') }}</p>
                <a class="hs-customer-orders-back" href="{{ route('store.account.index') }}">{{ __('store.customer_orders.back_to_account') }}</a>
            </div>

            @if ($orders->isEmpty())
                <div class="hs-account-empty hs-customer-orders-empty">
                    <p>{{ __('store.customer_orders.empty') }}</p>
                    <a class="btn hs-button hs-button-dark" href="{{ route('store.shop') }}">{{ __('store.cart.continue_shopping') }}</a>
                </div>
            @else
                <div class="hs-customer-order-list">
                    @foreach ($orders as $order)
                        <article class="hs-customer-order-card">
                            <div class="hs-customer-order-main">
                                <div>
                                    <span class="hs-customer-order-label">{{ __('store.customer_orders.order_number') }}</span>
                                    <h2>{{ $order->order_number }}</h2>
                                    <p>{{ \Illuminate\Support\Carbon::parse($order->placed_at)->locale($locale)->translatedFormat('d M Y · h:i A') }}</p>
                                </div>
                                <span class="hs-customer-order-status">{{ $order->status_label }}</span>
                            </div>
                            <div class="hs-customer-order-meta">
                                <span>{{ __('store.customer_orders.items_count', ['count' => $order->item_count ?? 0]) }}</span>
                                <strong>{{ $order->grand_total ?? $order->order_grand_total }} {{ __('store.products.currency') }}</strong>
                            </div>
                            <a class="btn hs-button hs-button-dark" href="{{ route('store.account.orders.show', $order->public_id) }}">{{ __('store.customer_orders.view_details') }}</a>
                        </article>
                    @endforeach
                </div>

                <div class="hs-customer-orders-pagination mt-4">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
@endsection
