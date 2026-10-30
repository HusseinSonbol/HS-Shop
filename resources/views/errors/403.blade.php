<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.errors.forbidden_title') }} · {{ __('admin.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-body">
    <main class="forbidden-page">
        <img src="{{ asset('images/hs-shop-logo.png') }}" alt="{{ __('admin.brand') }}">
        <p class="eyebrow">403</p>
        <h1>{{ __('admin.errors.forbidden_title') }}</h1>
        <p>{{ __('admin.errors.forbidden_description') }}</p>
        <a class="primary-button" href="{{ route('admin.login') }}">{{ __('admin.errors.return_to_login') }}</a>
    </main>
</body>
</html>
