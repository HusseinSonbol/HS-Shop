@foreach (['success' => ['cart_status', 'favorite_status', 'account_status'], 'info' => ['account_notice']] as $type => $keys)
    @foreach ($keys as $key)
        @if (session($key))
            <div class="container hs-shell hs-store-flash-wrap">
                <div class="alert alert-{{ $type }} mb-0" role="status">{{ session($key) }}</div>
            </div>
        @endif
    @endforeach
@endforeach
