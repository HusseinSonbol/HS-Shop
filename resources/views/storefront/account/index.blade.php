@extends('storefront.layouts.rapidshop')

@section('title', __('store.customer_account.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page">
        <div class="container hs-shell">
            <div class="hs-account-heading mb-4">
                <span class="hs-eyebrow">{{ __('store.brand') }}</span>
                <h1>{{ __('store.customer_account.title') }}</h1>
                <p>{{ __('store.customer_account.description') }}</p>
                <a class="btn hs-button hs-button-dark mt-3" href="{{ route('store.account.orders.index') }}">{{ __('store.customer_orders.title') }}</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <section class="hs-account-panel h-100">
                        <h2>{{ __('store.customer_account.profile') }}</h2>
                        <form method="POST" action="{{ route('store.account.profile.update') }}" class="hs-account-form">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label" for="account-name">{{ __('store.account.name') }}</label>
                                <input id="account-name" class="form-control hs-contact-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" maxlength="160" autocomplete="name" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="account-email">{{ __('store.account.email') }}</label>
                                <input id="account-email" class="form-control hs-contact-control" type="email" value="{{ $user->email }}" dir="ltr" readonly aria-describedby="account-email-note">
                                <small id="account-email-note" class="form-text">{{ __('store.customer_account.email_change_note') }}</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="account-phone">{{ __('store.account.phone') }}</label>
                                <input id="account-phone" class="form-control hs-contact-control @error('phone') is-invalid @enderror" type="tel" name="phone" value="{{ old('phone', $user->phone ?? $customer->phone) }}" maxlength="32" autocomplete="tel" dir="ltr" required>
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn hs-button hs-button-dark" type="submit">{{ __('store.customer_account.save_profile') }}</button>
                        </form>
                    </section>
                </div>

                <div class="col-lg-7">
                    <section class="hs-account-panel">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                            <div>
                                <h2 class="mb-1">{{ __('store.customer_account.addresses') }}</h2>
                                <p class="mb-0 text-secondary small">{{ __('store.customer_account.addresses_description') }}</p>
                            </div>
                            <a class="btn hs-button hs-button-dark" href="{{ route('store.account.addresses.create') }}">{{ __('store.customer_account.add_address') }}</a>
                        </div>

                        @if ($addresses->isEmpty())
                            <div class="hs-account-empty">{{ __('store.customer_account.no_addresses') }}</div>
                        @else
                            <div class="d-grid gap-3">
                                @foreach ($addresses as $address)
                                    <article class="hs-account-address">
                                        <div class="d-flex align-items-start justify-content-between gap-3">
                                            <div>
                                                <h3>{{ $address->label ?: __('store.customer_account.address') }}</h3>
                                                @if ($address->is_default)<span class="badge text-bg-light">{{ __('store.customer_account.default_address') }}</span>@endif
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('store.account.addresses.edit', $address) }}">{{ __('store.customer_account.edit') }}</a>
                                                <form method="POST" action="{{ route('store.account.addresses.archive', $address) }}" onsubmit="return confirm(@js(__('store.customer_account.confirm_archive_address')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('store.customer_account.archive') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                        <p class="mb-1"><strong>{{ $address->recipient_name }}</strong> · <span dir="ltr">{{ $address->phone }}</span></p>
                                        <p class="mb-0">{{ collect([$address->street, $address->building, $address->floor, $address->apartment, $address->district, $address->city, $address->governorate])->filter()->join('، ') }}</p>
                                        @if ($address->landmark)<small class="text-secondary">{{ __('store.customer_account.landmark') }}: {{ $address->landmark }}</small>@endif
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
