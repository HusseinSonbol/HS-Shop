<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('store.footer.description') }}">
    <meta name="theme-color" content="#0B132B">
    <title>@yield('title', __('store.brand'))</title>
    @if ($isRtl)
        @vite('resources/css/admin-bootstrap.rtl.css')
    @else
        @vite('resources/css/storefront-bootstrap5.css')
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/hs-shop-rapidshop.css') }}">
    @stack('head')
</head>
<body class="hs-storefront">
    @include('storefront.partials.rapidshop-header')

    <main id="main-content">
        @include('storefront.partials.flash-messages')
        @yield('content')
    </main>

    @include('storefront.partials.rapidshop-footer')

    @vite('resources/js/storefront-bootstrap5.js')
    @stack('scripts')
</body>
</html>
