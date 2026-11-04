@extends('admin.layouts.app')

@section('title', __('admin.social_orders.create_title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.orders.title') }}</p>
            <h1>{{ __('admin.social_orders.create_title') }}</h1>
            <p class="page-description">{{ __('admin.social_orders.create_description') }}</p>
        </div>
        <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.index') }}">{{ __('admin.orders.back') }}</a>
    </section>

    @if ($customers->isEmpty())
        <div class="alert alert-warning" role="alert">{{ __('admin.social_orders.no_customers') }} @if (auth()->user()->hasPermission('customers.manage'))<a href="{{ route('admin.customers.create') }}">{{ __('admin.customers.create') }}</a>@endif</div>
    @endif
    @if ($variants->isEmpty())
        <div class="alert alert-warning" role="alert">{{ __('admin.social_orders.no_variants') }}</div>
    @endif
    @if ($shippingRates === [])
        <div class="alert alert-warning" role="alert">{{ __('admin.social_orders.no_shipping_rates') }}</div>
    @endif

    <form class="card panel social-order-form" method="POST" action="{{ route('admin.orders.store-social') }}" enctype="multipart/form-data" data-social-order-form>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <section class="social-order-form-section">
            <h2>{{ __('admin.social_orders.customer_and_channel') }}</h2>
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="social-customer">{{ __('admin.social_orders.customer') }}</label>
                    <select class="form-select @error('customer_id') is-invalid @enderror" id="social-customer" name="customer_id" required>
                        <option value="">—</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->display_name }} · {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="social-channel">{{ __('admin.social_orders.channel') }}</label>
                    <select class="form-select @error('channel') is-invalid @enderror" id="social-channel" name="channel" required>
                        <option value="facebook" @selected(old('channel', 'facebook') === 'facebook')>{{ __('admin.orders.channel_values.facebook') }}</option>
                        <option value="instagram" @selected(old('channel') === 'instagram')>{{ __('admin.orders.channel_values.instagram') }}</option>
                    </select>
                    @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="source-reference">{{ __('admin.social_orders.source_reference') }}</label>
                    <input class="form-control @error('source_reference') is-invalid @enderror" id="source-reference" name="source_reference" value="{{ old('source_reference') }}" maxlength="120">
                    @error('source_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="social-order-form-section">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <h2 class="mb-0">{{ __('admin.social_orders.items') }}</h2>
                <button class="btn btn-primary primary-button" type="button" data-add-order-item>{{ __('admin.social_orders.add_item') }}</button>
            </div>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="social-order-lines" data-order-lines>
                @foreach (old('items', [['variant_id' => '', 'quantity' => '1']]) as $index => $item)
                    <div class="row g-3 align-items-end social-order-line" data-order-line>
                        <div class="col-lg-9">
                            <label class="form-label" for="variant-{{ $index }}">{{ __('admin.social_orders.product_variant') }}</label>
                            <select class="form-select @error('items.'.$index.'.variant_id') is-invalid @enderror" id="variant-{{ $index }}" name="items[{{ $index }}][variant_id]" required>
                                <option value="">—</option>
                                @foreach ($variants as $variant)
                                    <option value="{{ $variant['id'] }}" @selected((string) ($item['variant_id'] ?? '') === (string) $variant['id'])>{{ $variant['label'] }}</option>
                                @endforeach
                            </select>
                            @error('items.'.$index.'.variant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-8 col-lg-2">
                            <label class="form-label" for="quantity-{{ $index }}">{{ __('admin.social_orders.quantity') }}</label>
                            <input class="form-control @error('items.'.$index.'.quantity') is-invalid @enderror" id="quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="1" max="1000" step="1" value="{{ $item['quantity'] ?? '1' }}" required>
                            @error('items.'.$index.'.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4 col-lg-1"><button class="btn btn-outline-danger w-100" type="button" data-remove-order-item aria-label="{{ __('admin.social_orders.remove_item') }}">×</button></div>
                    </div>
                @endforeach
            </div>
            <template data-order-line-template>
                <div class="row g-3 align-items-end social-order-line" data-order-line>
                    <div class="col-lg-9">
                        <label class="form-label" for="variant-__INDEX__">{{ __('admin.social_orders.product_variant') }}</label>
                        <select class="form-select" id="variant-__INDEX__" name="items[__INDEX__][variant_id]" required>
                            <option value="">—</option>
                            @foreach ($variants as $variant)<option value="{{ $variant['id'] }}">{{ $variant['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-8 col-lg-2"><label class="form-label" for="quantity-__INDEX__">{{ __('admin.social_orders.quantity') }}</label><input class="form-control" id="quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="1" max="1000" step="1" value="1" required></div>
                    <div class="col-4 col-lg-1"><button class="btn btn-outline-danger w-100" type="button" data-remove-order-item aria-label="{{ __('admin.social_orders.remove_item') }}">×</button></div>
                </div>
            </template>
        </section>

        <section class="social-order-form-section">
            <h2>{{ __('admin.social_orders.shipping_address') }}</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="recipient-name">{{ __('admin.orders.recipient') }}</label><input class="form-control" id="recipient-name" name="shipping_address[recipient_name]" value="{{ old('shipping_address.recipient_name') }}" maxlength="160" required>@error('shipping_address.recipient_name')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="recipient-phone">{{ __('admin.customers.phone') }}</label><input class="form-control" id="recipient-phone" name="shipping_address[phone]" value="{{ old('shipping_address.phone') }}" maxlength="32" required dir="ltr">@error('shipping_address.phone')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="shipping-rate">{{ __('admin.social_orders.governorate_and_shipping') }}</label><select class="form-select" id="shipping-rate" name="shipping_rate_code" required><option value="">—</option>@foreach ($shippingRates as $rate)<option value="{{ $rate['code'] }}" @selected(old('shipping_rate_code') === $rate['code'])>{{ app()->getLocale() === 'ar' ? $rate['governorate_ar'] : $rate['governorate_en'] }} · {{ __('admin.social_orders.shipping_charge') }} EGP {{ $rate['charge'] }}</option>@endforeach</select>@error('shipping_rate_code')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="shipping-city">{{ __('admin.customers.city') }}</label><input class="form-control" id="shipping-city" name="shipping_address[city]" value="{{ old('shipping_address.city') }}" maxlength="120" required>@error('shipping_address.city')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="shipping-district">{{ __('admin.customers.district') }}</label><input class="form-control" id="shipping-district" name="shipping_address[district]" value="{{ old('shipping_address.district') }}" maxlength="120"></div>
                <div class="col-md-6"><label class="form-label" for="shipping-street">{{ __('admin.customers.street') }}</label><input class="form-control" id="shipping-street" name="shipping_address[street]" value="{{ old('shipping_address.street') }}" maxlength="255" required>@error('shipping_address.street')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="shipping-building">{{ __('admin.customers.building') }}</label><input class="form-control" id="shipping-building" name="shipping_address[building]" value="{{ old('shipping_address.building') }}" maxlength="80"></div>
                <div class="col-md-4"><label class="form-label" for="shipping-floor">{{ __('admin.customers.floor') }}</label><input class="form-control" id="shipping-floor" name="shipping_address[floor]" value="{{ old('shipping_address.floor') }}" maxlength="80"></div>
                <div class="col-md-4"><label class="form-label" for="shipping-apartment">{{ __('admin.customers.apartment') }}</label><input class="form-control" id="shipping-apartment" name="shipping_address[apartment]" value="{{ old('shipping_address.apartment') }}" maxlength="80"></div>
                <div class="col-md-6"><label class="form-label" for="shipping-landmark">{{ __('admin.customers.landmark') }}</label><input class="form-control" id="shipping-landmark" name="shipping_address[landmark]" value="{{ old('shipping_address.landmark') }}" maxlength="255"></div>
                <div class="col-md-6"><label class="form-label" for="shipping-postal">{{ __('admin.customers.postal_code') }}</label><input class="form-control" id="shipping-postal" name="shipping_address[postal_code]" value="{{ old('shipping_address.postal_code') }}" maxlength="20"></div>
            </div>
        </section>

        <section class="social-order-form-section">
            <h2>{{ __('admin.social_orders.payment') }}</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="payment-method">{{ __('admin.social_orders.payment_method') }}</label><select class="form-select" id="payment-method" name="payment_method" required data-payment-method><option value="cod" @selected(old('payment_method', 'cod') === 'cod')>{{ __('admin.social_orders.methods.cod') }}</option><option value="instapay" @selected(old('payment_method') === 'instapay')>{{ __('admin.social_orders.methods.instapay') }}</option></select>@error('payment_method')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="customer-note">{{ __('admin.social_orders.internal_note') }}</label><textarea class="form-control" id="customer-note" name="customer_note" rows="2" maxlength="2000">{{ old('customer_note') }}</textarea>@error('customer_note')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-12" data-proof-upload @if (old('payment_method', 'cod') !== 'instapay') hidden @endif>
                    <label class="form-label" for="payment-proof">{{ __('admin.social_orders.payment_proof') }}</label>
                    <input class="form-control @error('payment_proof') is-invalid @enderror" id="payment-proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp" @if (old('payment_method') === 'instapay') required @endif>
                    <div class="form-text">{{ __('admin.social_orders.proof_help') }}</div>
                    @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <div class="social-order-form-actions d-flex gap-2 flex-wrap">
            <button class="btn btn-primary primary-button" type="submit" @disabled($customers->isEmpty() || $variants->isEmpty() || $shippingRates === [])>{{ __('admin.social_orders.save_order') }}</button>
            <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.index') }}">{{ __('admin.orders.back') }}</a>
        </div>
    </form>
@endsection
