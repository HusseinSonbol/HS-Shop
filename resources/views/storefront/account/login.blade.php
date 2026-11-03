@extends('storefront.layouts.rapidshop')

@section('title', __('store.account.login_title').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page">
        <div class="container hs-shell">
            <div class="hs-account-card">
                <span class="hs-eyebrow">{{ __('store.brand') }}</span>
                <h1>{{ __('store.account.login_title') }}</h1>
                <p class="hs-account-intro">{{ __('store.account.login_description') }}</p>

                <form method="POST" action="{{ route('store.account.login.store') }}" class="hs-account-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="customer-email">{{ __('store.account.email') }}</label>
                        <input id="customer-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required class="form-control hs-contact-control @error('email') is-invalid @enderror" dir="ltr">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer-password">{{ __('store.account.password') }}</label>
                        <input id="customer-password" name="password" type="password" autocomplete="current-password" required class="form-control hs-contact-control @error('password') is-invalid @enderror" dir="ltr">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn hs-button hs-button-dark w-100" type="submit">{{ __('store.account.login_action') }}</button>
                </form>

                <p class="hs-account-switch">{{ __('store.account.no_account') }} <a href="{{ route('store.account.register') }}">{{ __('store.account.register_action') }}</a></p>
            </div>
        </div>
    </section>
@endsection
