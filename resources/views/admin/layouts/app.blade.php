<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.admin_panel')) · {{ __('admin.brand') }}</title>
    @if (app()->getLocale() === 'ar')
        @vite(['resources/css/admin-bootstrap.rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/admin-bootstrap.css', 'resources/js/app.js'])
    @endif
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="brand-lockup" href="{{ route('admin.dashboard') }}">
                <img src="{{ asset('images/hs-shop-logo.png') }}" alt="{{ __('admin.brand') }}">
                <span>{{ __('admin.admin_panel') }}</span>
            </a>

            <nav class="side-navigation nav flex-column" aria-label="{{ __('admin.admin_panel') }}">
                <a class="side-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <span class="nav-mark" aria-hidden="true">⌂</span>
                    <span>{{ __('admin.navigation.home') }}</span>
                </a>
                @if (auth()->user()->hasPermission('users.manage'))
                    <a class="side-link" href="{{ route('admin.staff.index') }}">
                        <span class="nav-mark" aria-hidden="true">◎</span>
                        <span>{{ __('admin.staff.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('customers.manage'))
                    <a class="side-link {{ request()->routeIs('admin.customers.*') ? 'is-active' : '' }}" href="{{ route('admin.customers.index') }}" @if (request()->routeIs('admin.customers.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">♙</span>
                        <span>{{ __('admin.customers.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('catalog.manage'))
                    <a class="side-link {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}" href="{{ route('admin.products.index') }}" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">▣</span>
                        <span>{{ __('admin.products.navigation') }}</span>
                    </a>
                    <a class="side-link {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}" @if (request()->routeIs('admin.categories.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">▦</span>
                        <span>{{ __('admin.categories.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('suppliers.manage'))
                    <a class="side-link {{ request()->routeIs('admin.suppliers.*') ? 'is-active' : '' }}" href="{{ route('admin.suppliers.index') }}" @if (request()->routeIs('admin.suppliers.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">▧</span>
                        <span>{{ __('admin.suppliers.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('homepage.manage'))
                    <a class="side-link {{ request()->routeIs('admin.homepage.*') ? 'is-active' : '' }}" href="{{ route('admin.homepage.index') }}" @if (request()->routeIs('admin.homepage.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">▤</span>
                        <span>{{ __('admin.homepage.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('settings.manage'))
                    <a class="side-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}" href="{{ route('admin.settings.shipping-rates.edit') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">↗</span>
                        <span>{{ __('admin.shipping_rates.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('faqs.manage'))
                    <a class="side-link {{ request()->routeIs('admin.about-faqs.*') ? 'is-active' : '' }}" href="{{ route('admin.about-faqs.index') }}" @if (request()->routeIs('admin.about-faqs.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">?</span>
                        <span>{{ __('admin.about_faqs.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('reviews.moderate'))
                    <a class="side-link {{ request()->routeIs('admin.reviews.*') ? 'is-active' : '' }}" href="{{ route('admin.reviews.index') }}" @if (request()->routeIs('admin.reviews.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">★</span>
                        <span>{{ __('admin.reviews.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('feedback.moderate'))
                    <a class="side-link {{ request()->routeIs('admin.feedback.*') ? 'is-active' : '' }}" href="{{ route('admin.feedback.index') }}" @if (request()->routeIs('admin.feedback.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">✎</span>
                        <span>{{ __('admin.feedback.navigation') }}</span>
                    </a>
                @endif
                @if (auth()->user()->hasPermission('contact_messages.manage'))
                    <a class="side-link {{ request()->routeIs('admin.contact-messages.*') ? 'is-active' : '' }}" href="{{ route('admin.contact-messages.index') }}" @if (request()->routeIs('admin.contact-messages.*')) aria-current="page" @endif>
                        <span class="nav-mark" aria-hidden="true">✉</span>
                        <span>{{ __('admin.contact_messages.navigation') }}</span>
                    </a>
                @endif
            </nav>

            <a class="storefront-link" href="{{ url('/') }}" target="_blank" rel="noopener noreferrer">
                {{ __('admin.navigation.storefront') }}
                <span aria-hidden="true">↗</span>
            </a>
        </aside>

        <div class="admin-main-column">
            <header class="admin-topbar d-flex align-items-center justify-content-between">
                <div class="topbar-title">{{ __('admin.admin_panel') }}</div>
                <div class="topbar-actions d-flex align-items-center">
                    @include('admin.partials.locale-switcher')
                    <span class="user-name">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="btn btn-link sign-out-button" type="submit">{{ __('admin.logout.button') }}</button>
                    </form>
                </div>
            </header>

            <main class="admin-content">
                @if (session('status'))
                    <div class="alert alert-success flash-message" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
