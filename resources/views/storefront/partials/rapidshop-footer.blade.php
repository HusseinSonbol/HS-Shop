<footer class="hs-footer" id="site-footer">
    <div class="container hs-shell">
        <div class="row gy-5">
            <div class="col-lg-5">
                <a class="hs-footer-brand" href="{{ route('store.home') }}">
                    <img src="{{ asset('images/hs-shop-logo.png') }}" alt="{{ __('store.brand') }}" width="136" height="64" loading="lazy">
                </a>
                <p class="hs-footer-description">{{ __('store.footer.description') }}</p>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="hs-footer-heading">{{ __('store.footer.quick_links') }}</h2>
                <ul class="hs-footer-links list-unstyled mb-0">
                    <li><a href="{{ route('store.home') }}">{{ __('store.navigation.home') }}</a></li>
                    <li><a href="{{ route('store.shop') }}">{{ __('store.navigation.shop') }}</a></li>
                    <li><a href="{{ route('store.about') }}">{{ __('store.navigation.about') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h2 class="hs-footer-heading">{{ __('store.footer.contact') }}</h2>
                <p class="hs-footer-description">{{ __('store.footer.contact_note') }}</p>
                <a class="hs-footer-contact-link" href="{{ route('store.home') }}#hs-contact">{{ __('store.footer.contact') }}</a>
            </div>
        </div>
        <div class="hs-footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span>© {{ now()->year }} HS Shop. {{ __('store.footer.rights') }}</span>
            <span class="hs-footer-made">{{ __('store.hero.badge') }}</span>
        </div>
    </div>
</footer>
