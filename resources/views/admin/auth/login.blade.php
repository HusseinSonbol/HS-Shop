<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.login.title') }} · {{ __('admin.brand') }}</title>
    @if (app()->getLocale() === 'ar')
        @vite(['resources/css/admin-bootstrap.rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/admin-bootstrap.css', 'resources/js/app.js'])
    @endif
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-body">
    <main class="login-layout">
        <section class="login-brand-panel" aria-label="{{ __('admin.brand') }}">
            <div class="brand-panel-content">
                <img class="login-logo" src="{{ asset('images/hs-shop-logo.png') }}" alt="{{ __('admin.brand') }}">
                <span class="brand-panel-rule"></span>
                <p>{{ __('admin.admin_panel') }}</p>
            </div>
            <div class="brand-panel-caption">HS SHOP <span>·</span> CAIRO</div>
        </section>

        <section class="login-form-panel">
            <div class="login-tools">
                @include('admin.partials.locale-switcher')
            </div>

            <div class="login-card">
                <p class="eyebrow">{{ __('admin.login.eyebrow') }}</p>
                <h1>{{ __('admin.login.title') }}</h1>
                <p class="login-subtitle">{{ __('admin.login.subtitle') }}</p>

                @if ($errors->any())
                    <div class="alert alert-danger form-alert" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success form-success" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="login-form">
                    @csrf
                    <div class="form-field">
                        <label class="form-label" for="email">{{ __('admin.login.email') }}</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            inputmode="email"
                            maxlength="190"
                            required
                            class="form-control"
                            autofocus
                            dir="ltr"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            @if ($errors->has('email')) aria-describedby="email-error" @endif
                        >
                        @error('email')
                            <span class="field-error" id="email-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="password">{{ __('admin.login.password') }}</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="form-control"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            @if ($errors->has('password')) aria-describedby="password-error" @endif
                        >
                        @error('password')
                            <span class="field-error" id="password-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <label class="remember-control" for="remember">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <span>{{ __('admin.login.remember') }}</span>
                    </label>

                    <button class="btn btn-primary primary-button login-submit" type="submit">
                        {{ __('admin.login.submit') }}
                        <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                    </button>
                </form>
            </div>

            <footer class="login-footer">© {{ date('Y') }} {{ __('admin.brand') }}</footer>
        </section>
    </main>
</body>
</html>
