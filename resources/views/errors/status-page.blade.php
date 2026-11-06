@php
    $errorLocale = app()->getLocale();
    if (! request()->route() && request()->getPreferredLanguage(['ar', 'en']) === 'ar') {
        $errorLocale = 'ar';
    }
@endphp
<!doctype html>
<html lang="{{ $errorLocale }}" dir="{{ $errorLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ \Illuminate\Support\Facades\Lang::get('errors.status.'.$statusCode.'.title', [], $errorLocale) }} · HS Shop</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #18233f; background: #f6f7fa; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; }
        main { width: min(100%, 560px); padding: clamp(28px, 7vw, 56px); border: 1px solid #e5e8ef; border-radius: 24px; background: #fff; box-shadow: 0 18px 60px #18233f12; text-align: center; }
        img { width: min(180px, 48vw); height: auto; margin-bottom: 24px; }
        .code { margin: 0 0 8px; color: #d39d36; font-size: 14px; font-weight: 800; letter-spacing: .18em; }
        h1 { margin: 0 0 14px; font-size: clamp(26px, 6vw, 38px); }
        p { margin: 0 auto 26px; color: #687187; line-height: 1.8; }
        a { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; padding: 10px 22px; border-radius: 9px; background: #18233f; color: #fff; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid #d39d36; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/hs-shop-logo.png') }}" alt="HS Shop">
        <p class="code">{{ $statusCode }}</p>
        <h1>{{ \Illuminate\Support\Facades\Lang::get('errors.status.'.$statusCode.'.title', [], $errorLocale) }}</h1>
        <p>{{ \Illuminate\Support\Facades\Lang::get('errors.status.'.$statusCode.'.description', [], $errorLocale) }}</p>
        <a href="{{ route('store.home') }}">{{ \Illuminate\Support\Facades\Lang::get('errors.back_to_store', [], $errorLocale) }}</a>
    </main>
</body>
</html>
