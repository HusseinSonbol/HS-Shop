@extends('storefront.layouts.rapidshop')

@section('title', __('store.checkout.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-product-breadcrumb-band">
        <div class="container hs-shell">
            <nav aria-label="{{ __('store.navigation.menu') }}">
                <ol class="hs-product-breadcrumb">
                    <li><a href="{{ route('store.home') }}">{{ __('store.navigation.home') }}</a></li>
                    <li><a href="{{ route('store.cart.index') }}">{{ __('store.cart.title') }}</a></li>
                    <li aria-current="page">{{ __('store.checkout.breadcrumb') }}</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="hs-cart-page hs-checkout-page">
        <div class="container hs-shell">
            <div class="hs-cart-heading">
                <span class="hs-eyebrow">HS Shop</span>
                <h1>{{ __('store.checkout.title') }}</h1>
                <p>{{ __('store.checkout.summary') }}</p>
            </div>

            @if ($items->isEmpty())
                <div class="hs-cart-empty">
                    <span class="hs-cart-empty-icon" aria-hidden="true">♡</span>
                    <p>{{ __('store.checkout.empty') }}</p>
                    <a class="btn hs-button hs-button-dark" href="{{ route('store.shop') }}">{{ __('store.cart.continue_shopping') }}</a>
                </div>
            @else
                @if ($has_unavailable_items)
                    <div class="alert alert-warning" role="status">{{ __('store.cart.unavailable_items') }} <a href="{{ route('store.cart.index') }}">{{ __('store.cart.title') }}</a></div>
                @endif
                <div class="row g-4 g-xl-5 align-items-start">
                    <div class="col-lg-8">
                        <div class="hs-checkout-items">
                            @foreach ($items as $item)
                                <article class="hs-checkout-item @if (! $item->storefront_is_available) is-unavailable @endif">
                                    @if ($item->storefront_image_url)
                                        <img src="{{ $item->storefront_image_url }}" alt="{{ $item->storefront_product_name }}" loading="lazy" width="78" height="92">
                                    @else
                                        <span class="hs-image-placeholder" aria-hidden="true">HS</span>
                                    @endif
                                    <div class="hs-checkout-item-copy">
                                        <h2>{{ $item->storefront_product_name }}</h2>
                                        <p>{{ __('store.cart.option') }}: {{ $item->storefront_variant_label }}</p>
                                        @if (! $item->storefront_is_available)<span class="hs-cart-unavailable-label">{{ __('store.cart.unavailable') }}</span>@endif
                                    </div>
                                    <div class="hs-checkout-item-price">
                                        <span>{{ __('store.cart.quantity') }}: {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</span>
                                        <strong>{{ $item->storefront_line_total ?? '—' }} @if ($item->storefront_line_total){{ __('store.products.currency') }}@endif</strong>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <aside class="col-lg-4">
                        <div class="hs-cart-summary hs-checkout-summary">
                            <h2>{{ __('store.cart.summary') }}</h2>
                            <div class="hs-cart-summary-line"><span>{{ __('store.cart.subtotal') }}</span><strong>{{ $subtotal }} {{ __('store.products.currency') }}</strong></div>
                            <div class="hs-cart-summary-line"><span>{{ __('store.cart.shipping') }}</span><strong id="checkout-shipping-amount">{{ $selectedQuote['charge'] ?? '—' }} @if ($selectedQuote){{ __('store.products.currency') }}@endif</strong></div>
                            <div class="hs-cart-summary-line"><span>{{ __('store.checkout.grand_total') }}</span><strong id="checkout-grand-total">{{ $selectedQuote['grand_total'] ?? '—' }} @if ($selectedQuote){{ __('store.products.currency') }}@endif</strong></div>
                            <div class="hs-cart-summary-payment"><span>{{ __('store.checkout.payment_method') }}</span><strong>{{ __('store.checkout.cash_on_delivery') }}</strong></div>
                            @if ($has_unavailable_items)
                                <a class="btn hs-button hs-button-dark w-100" href="{{ route('store.cart.index') }}">{{ __('store.cart.title') }}</a>
                            @elseif ($isCustomer)
                                @if (empty($rates))
                                    <p class="hs-checkout-notice" role="alert">{{ __('store.checkout.no_shipping_rates') }}</p>
                                @else
                                    <p class="hs-checkout-notice" role="status">{{ __('store.checkout.price_confirmation_note') }}</p>
                                @endif
                            @else
                                <p class="hs-checkout-notice">{{ __('store.checkout.account_required') }}</p>
                                <div class="hs-checkout-account-actions">
                                    <a class="btn hs-button hs-button-dark w-100" href="{{ route('store.account.login') }}">{{ __('store.checkout.login_action') }}</a>
                                    <a class="btn hs-button hs-button-outline w-100" href="{{ route('store.account.register') }}">{{ __('store.checkout.register_action') }}</a>
                                </div>
                            @endif
                            <p class="hs-cart-summary-note">{{ __('store.cart.current_price_note') }}</p>
                        </div>
                    </aside>
                </div>

                @if ($isCustomer && ! $has_unavailable_items && count($rates) > 0)
                    <form id="checkout-place-form" method="POST" action="{{ route('store.checkout.place') }}" class="hs-checkout-form mt-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <input type="hidden" name="quote_token" id="checkout-quote-token" value="{{ $selectedQuote['token'] ?? '' }}">
                        <input type="hidden" name="shipping_rate_code" id="checkout-shipping-rate-code" value="{{ $selectedRateCode ?? '' }}">
                        <section class="hs-checkout-address-panel">
                            <div class="hs-checkout-section-heading">
                                <div><span class="hs-eyebrow">{{ __('store.checkout.delivery') }}</span><h2>{{ __('store.checkout.delivery_address') }}</h2></div>
                            </div>

                            <div class="alert hs-checkout-address-guidance" role="note">
                                <span class="hs-checkout-guidance-icon" aria-hidden="true">i</span>
                                <span>{{ __('store.checkout.choose_address_method') }}</span>
                            </div>

                            <div class="visually-hidden" aria-hidden="true">
                                @if ($addresses->isNotEmpty())
                                    <input type="radio" name="address_source" value="saved" data-address-source-radio @checked($addressSource === 'saved')>
                                @endif
                                <input type="radio" name="address_source" value="new" data-address-source-radio @checked($addressSource === 'new')>
                            </div>

                            <div class="hs-checkout-address-tabs" role="tablist" aria-label="{{ __('store.checkout.delivery_address') }}">
                                @if ($addresses->isNotEmpty())
                                    <button class="hs-checkout-address-tab @if ($addressSource === 'saved') is-active @endif" type="button" role="tab" id="checkout-saved-tab" aria-controls="checkout-saved-panel" aria-selected="{{ $addressSource === 'saved' ? 'true' : 'false' }}" data-address-tab="saved">
                                        <span class="hs-checkout-tab-icon" aria-hidden="true">⌂</span>
                                        <span>{{ __('store.checkout.saved_addresses') }}</span>
                                    </button>
                                @endif
                                <button class="hs-checkout-address-tab @if ($addressSource === 'new') is-active @endif" type="button" role="tab" id="checkout-new-tab" aria-controls="checkout-new-panel" aria-selected="{{ $addressSource === 'new' ? 'true' : 'false' }}" data-address-tab="new">
                                    <span class="hs-checkout-tab-icon" aria-hidden="true">＋</span>
                                    <span>{{ __('store.checkout.new_address') }}</span>
                                </button>
                            </div>

                            @if ($addresses->isNotEmpty())
                                <div id="checkout-saved-panel" class="hs-checkout-address-tab-panel" role="tabpanel" aria-labelledby="checkout-saved-tab" data-address-panel="saved" @if ($addressSource !== 'saved') hidden @endif>
                                    <div class="hs-checkout-saved-addresses" data-checkout-saved-addresses>
                                    @foreach ($addresses as $address)
                                        <label class="hs-checkout-address-choice @if ((string) $selectedAddressId === (string) $address->id && $addressSource === 'saved') is-selected @endif">
                                            <input class="form-check-input" type="radio" name="address_id" value="{{ $address->id }}" data-rate-code="{{ $addressRateCodes[$address->id] ?? '' }}" @checked((string) $selectedAddressId === (string) $address->id && $addressSource === 'saved')>
                                            <span class="hs-checkout-address-copy">
                                                <strong>{{ $address->label ?: $address->recipient_name }}</strong>
                                                @if ($address->is_default)<small>{{ __('store.customer_account.default_address') }}</small>@endif
                                                <span>{{ $address->recipient_name }} · <bdi>{{ $address->phone }}</bdi></span>
                                                <span>{{ collect([$address->street, $address->building, $address->district, $address->city, $address->governorate])->filter()->join('، ') }}</span>
                                                @if (! ($addressRateCodes[$address->id] ?? null))<em>{{ __('store.checkout.address_rate_missing') }}</em>@endif
                                            </span>
                                        </label>
                                    @endforeach
                                    </div>
                                </div>
                            @endif

                            <div id="checkout-new-panel" class="hs-checkout-address-tab-panel" role="tabpanel" aria-labelledby="checkout-new-tab" data-address-panel="new" @if ($addressSource !== 'new') hidden @endif>
                            <div class="hs-checkout-new-address" data-checkout-new-address>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="checkout-recipient">{{ __('store.customer_account.fields.recipient_name') }}</label>
                                        <input id="checkout-recipient" class="form-control hs-contact-control @error('shipping_address.recipient_name') is-invalid @enderror" name="shipping_address[recipient_name]" value="{{ old('shipping_address.recipient_name', $user?->name) }}" maxlength="160" autocomplete="name">
                                        @error('shipping_address.recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="checkout-phone">{{ __('store.customer_account.fields.phone') }}</label>
                                        <input id="checkout-phone" class="form-control hs-contact-control @error('shipping_address.phone') is-invalid @enderror" type="tel" name="shipping_address[phone]" value="{{ old('shipping_address.phone', $user?->phone ?? $customer?->phone) }}" maxlength="32" autocomplete="tel" dir="ltr">
                                        @error('shipping_address.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="checkout-governorate">{{ __('store.customer_account.fields.governorate') }}</label>
                                        <select id="checkout-governorate" class="form-select hs-contact-control @error('shipping_rate_code') is-invalid @enderror" data-checkout-governorate>
                                            <option value="">{{ __('store.checkout.choose_governorate') }}</option>
                                            @foreach ($rates as $rate)
                                                <option value="{{ $rate['code'] }}" data-quote-token="{{ $rateQuotes[$rate['code']]['token'] ?? '' }}" data-shipping-charge="{{ $rateQuotes[$rate['code']]['charge'] ?? '' }}" data-grand-total="{{ $rateQuotes[$rate['code']]['grand_total'] ?? '' }}" @selected($selectedRateCode === $rate['code'])>{{ app()->getLocale() === 'ar' ? $rate['governorate_ar'] : $rate['governorate_en'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('shipping_rate_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="checkout-city">{{ __('store.customer_account.fields.city') }}</label>
                                        <input id="checkout-city" class="form-control hs-contact-control @error('shipping_address.city') is-invalid @enderror" name="shipping_address[city]" value="{{ old('shipping_address.city') }}" maxlength="120">
                                        @error('shipping_address.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="checkout-street">{{ __('store.customer_account.fields.street') }}</label>
                                        <input id="checkout-street" class="form-control hs-contact-control @error('shipping_address.street') is-invalid @enderror" name="shipping_address[street]" value="{{ old('shipping_address.street') }}" maxlength="255" autocomplete="street-address">
                                        @error('shipping_address.street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    @foreach (['district', 'building', 'floor', 'apartment', 'landmark', 'postal_code', 'label'] as $field)
                                        <div class="col-md-6">
                                            <label class="form-label" for="checkout-{{ $field }}">{{ __('store.customer_account.fields.'.$field) }} <small class="text-secondary">({{ __('store.customer_account.optional') }})</small></label>
                                            <input id="checkout-{{ $field }}" class="form-control hs-contact-control @error('shipping_address.'.$field) is-invalid @enderror" name="shipping_address[{{ $field }}]" value="{{ old('shipping_address.'.$field) }}" maxlength="{{ $field === 'landmark' ? 255 : ($field === 'postal_code' ? 20 : 120) }}">
                                            @error('shipping_address.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    @endforeach
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input id="checkout-save-address" class="form-check-input" type="checkbox" name="save_address" value="1" @checked(old('save_address'))>
                                            <label class="form-check-label" for="checkout-save-address">{{ __('store.checkout.save_address') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                            @error('address_id')<p class="text-danger small mt-2">{{ $message }}</p>@enderror
                            @error('address_source')<p class="text-danger small mt-2">{{ $message }}</p>@enderror
                        </section>

                        <section class="hs-checkout-note-panel mt-3">
                            <label class="form-label" for="checkout-customer-note">{{ __('store.checkout.customer_note') }} <small class="text-secondary">({{ __('store.customer_account.optional') }})</small></label>
                            <textarea id="checkout-customer-note" class="form-control hs-contact-control @error('customer_note') is-invalid @enderror" name="customer_note" rows="3" maxlength="2000">{{ old('customer_note') }}</textarea>
                            @error('customer_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </section>

                        <div class="hs-checkout-submit-row">
                            <p>{{ __('store.checkout.cod_only_note') }}</p>
                            <button id="checkout-place-button" class="btn hs-button hs-button-dark" type="submit" @disabled(! $selectedQuote)>{{ __('store.checkout.place_order') }}</button>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('checkout-place-form');
            if (!form) return;

            const sourceInputs = Array.from(form.querySelectorAll('input[name="address_source"]'));
            const addressTabs = Array.from(form.querySelectorAll('[data-address-tab]'));
            const addressPanels = Array.from(form.querySelectorAll('[data-address-panel]'));
            const addressInputs = Array.from(form.querySelectorAll('input[name="address_id"]'));
            const newAddress = form.querySelector('[data-checkout-new-address]');
            const governorate = form.querySelector('[data-checkout-governorate]');
            const rateCode = form.querySelector('#checkout-shipping-rate-code');
            const quoteToken = form.querySelector('#checkout-quote-token');
            const submit = form.querySelector('#checkout-place-button');
            const shippingAmount = document.getElementById('checkout-shipping-amount');
            const grandTotal = document.getElementById('checkout-grand-total');
            const currency = @js(__('store.products.currency'));

            const selectedSource = () => sourceInputs.find((input) => input.checked)?.value ?? 'new';
            const selectedRateCode = () => {
                if (selectedSource() === 'saved') {
                    return addressInputs.find((input) => input.checked)?.dataset.rateCode ?? '';
                }
                return governorate?.value ?? '';
            };

            const refreshQuote = () => {
                const source = selectedSource();
                const useSaved = source === 'saved';
                addressTabs.forEach((tab) => {
                    const isActive = tab.dataset.addressTab === source;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    tab.tabIndex = isActive ? 0 : -1;
                });
                addressPanels.forEach((panel) => { panel.hidden = panel.dataset.addressPanel !== source; });
                if (newAddress) {
                    newAddress.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = useSaved; });
                    ['checkout-recipient', 'checkout-phone', 'checkout-city', 'checkout-street', 'checkout-governorate'].forEach((id) => {
                        const field = document.getElementById(id);
                        if (field) field.required = !useSaved;
                    });
                }
                addressInputs.forEach((input) => { input.disabled = !useSaved; });

                const code = selectedRateCode();
                const option = governorate ? Array.from(governorate.options).find((item) => item.value === code) : null;
                const token = option?.dataset.quoteToken ?? '';
                rateCode.value = code;
                quoteToken.value = token;
                submit.disabled = !token;

                const charge = option?.dataset.shippingCharge ?? '';
                const total = option?.dataset.grandTotal ?? '';
                shippingAmount.textContent = charge ? `${charge} ${currency}` : '—';
                grandTotal.textContent = total ? `${total} ${currency}` : '—';

                form.querySelectorAll('.hs-checkout-address-choice').forEach((label) => {
                    label.classList.toggle('is-selected', label.querySelector('input[name="address_id"]')?.checked ?? false);
                });
            };

            sourceInputs.forEach((input) => input.addEventListener('change', refreshQuote));
            addressTabs.forEach((tab, index) => {
                tab.addEventListener('click', () => {
                    const sourceInput = sourceInputs.find((input) => input.value === tab.dataset.addressTab);
                    if (!sourceInput) return;
                    sourceInput.checked = true;
                    refreshQuote();
                });
                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? addressTabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + addressTabs.length) % addressTabs.length;
                    addressTabs[nextIndex]?.focus();
                    addressTabs[nextIndex]?.click();
                });
            });
            addressInputs.forEach((input) => input.addEventListener('change', refreshQuote));
            governorate?.addEventListener('change', refreshQuote);
            refreshQuote();
        })();
    </script>
@endpush
