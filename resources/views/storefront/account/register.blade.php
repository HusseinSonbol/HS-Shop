@extends('storefront.layouts.rapidshop')

@section('title', __('store.account.register_title').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page">
        <div class="container hs-shell">
            <div class="hs-account-card">
                <span class="hs-eyebrow">{{ __('store.brand') }}</span>
                <h1>{{ __('store.account.register_title') }}</h1>
                <p class="hs-account-intro">{{ __('store.account.register_description') }}</p>

                <form method="POST" action="{{ route('store.account.register.store') }}" class="hs-account-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="customer-name">{{ __('store.account.name') }}</label>
                        <input id="customer-name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="160" required class="form-control hs-contact-control @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer-register-email">{{ __('store.account.email') }}</label>
                        <input id="customer-register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required class="form-control hs-contact-control @error('email') is-invalid @enderror" dir="ltr">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer-phone">{{ __('store.account.phone') }}</label>
                        <input id="customer-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="32" required class="form-control hs-contact-control @error('phone') is-invalid @enderror" dir="ltr">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer-register-password">{{ __('store.account.password') }}</label>
                        <input id="customer-register-password" name="password" type="password" autocomplete="new-password" minlength="9" maxlength="255" required class="form-control hs-contact-control @error('password') is-invalid @enderror" dir="ltr">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="customer-password-confirmation">{{ __('store.account.password_confirmation') }}</label>
                        <input id="customer-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="9" maxlength="255" required class="form-control hs-contact-control" dir="ltr">
                    </div>
                    <button class="btn hs-button hs-button-dark w-100" type="submit">{{ __('store.account.register_action') }}</button>
                </form>

                <p class="hs-account-switch">{{ __('store.account.have_account') }} <a href="{{ route('store.account.login') }}">{{ __('store.account.login_action') }}</a></p>
            </div>
        </div>
    </section>
@endsection
