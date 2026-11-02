<div class="hs-announcement">
    <div class="container hs-shell d-flex justify-content-center justify-content-md-between align-items-center gap-3">
        <span>{{ __('store.announcement') }}</span>
        <form method="post" action="{{ route('locale.update', $isRtl ? 'en' : 'ar') }}" class="hs-locale-form d-none d-md-block">
            @csrf
            <button type="submit" class="hs-locale-button" aria-label="{{ __('store.navigation.language') }}">
                <span aria-hidden="true">◎</span>
                {{ __('store.navigation.language') }}
            </button>
        </form>
    </div>
</div>

<header class="hs-header">
    <nav class="navbar navbar-expand-lg container hs-shell" aria-label="{{ __('store.navigation.menu') }}">
        <a class="navbar-brand hs-brand" href="{{ route('store.home') }}" aria-label="{{ __('store.brand') }}">
            <img src="{{ asset('images/hs-shop-logo.png') }}" alt="{{ __('store.brand') }}" width="150" height="70">
        </a>

        <button class="navbar-toggler hs-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#hsMainNavigation" aria-controls="hsMainNavigation" aria-expanded="false" aria-label="{{ __('store.navigation.menu') }}">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="hsMainNavigation">
            <ul class="navbar-nav mx-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link active" href="{{ route('store.home') }}" aria-current="page">{{ __('store.navigation.home') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('store.shop') }}">{{ __('store.navigation.shop') }}</a></li>
                <li class="nav-item dropdown hs-desktop-category-nav d-none d-lg-block">
                    <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ __('store.navigation.categories') }}</button>
                    <div class="dropdown-menu hs-category-megamenu">
                        <div class="hs-category-megamenu-heading">
                            <span class="hs-eyebrow">HS Shop</span>
                            <a href="{{ route('store.shop') }}">{{ __('store.categories.showing_all') }} <span aria-hidden="true">↗</span></a>
                        </div>
                        @if ($navigationCategories->isNotEmpty())
                            <ul class="hs-desktop-category-tree">
                                @foreach ($navigationCategories as $navigationCategory)
                                    @include('storefront.partials.desktop-category-tree-item', ['treeItem' => $navigationCategory])
                                @endforeach
                            </ul>
                        @else
                            <p class="hs-nav-categories-empty">{{ __('store.categories.empty') }}</p>
                        @endif
                    </div>
                </li>
                <li class="nav-item hs-mobile-category-nav d-lg-none">
                    <div class="hs-mobile-category-heading">
                        <a class="nav-link" href="{{ route('store.shop') }}">{{ __('store.navigation.categories') }}</a>
                    </div>
                    @if ($navigationCategories->isNotEmpty())
                        <ul class="hs-mobile-category-tree">
                            @foreach ($navigationCategories as $navigationCategory)
                                @include('storefront.partials.mobile-category-tree-item', ['treeItem' => $navigationCategory, 'level' => 0])
                            @endforeach
                        </ul>
                    @endif
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('store.about') }}">{{ __('store.navigation.about') }}</a></li>
            </ul>

            <div class="hs-header-actions d-flex align-items-center gap-2">
                @if (auth('customer')->check())
                    <form method="POST" action="{{ route('store.account.logout') }}" class="m-0">
                        @csrf
                        <button class="hs-icon-link hs-account-header-button" type="submit" aria-label="{{ __('store.account.logout_action') }}" title="{{ __('store.account.logout_action') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"></path><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path></svg>
                        </button>
                    </form>
                @else
                    <a class="hs-icon-link" href="{{ route('store.account.login') }}" aria-label="{{ __('store.account.login_title') }}" title="{{ __('store.account.login_title') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                    </a>
                @endif
                <a class="hs-icon-link" href="{{ route('store.shop') }}" aria-label="{{ __('store.navigation.shop') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                </a>
                <a class="btn hs-button hs-button-dark" href="{{ route('store.shop') }}">{{ __('store.navigation.shop') }}</a>
                <form method="post" action="{{ route('locale.update', $isRtl ? 'en' : 'ar') }}" class="hs-locale-form d-md-none">
                    @csrf
                    <button type="submit" class="hs-icon-link hs-locale-button" aria-label="{{ __('store.navigation.language') }}">{{ __('store.navigation.language') }}</button>
                </form>
            </div>
        </div>
    </nav>
</header>
