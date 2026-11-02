<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HS Shop — clothing and home furnishings.">
    <title>@yield('title', 'HS Shop')</title>
    @vite('resources/css/storefront-bootstrap5.css')

    <link href="{{ asset('emarket/css/font-awesome/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/js/datetimepicker/bootstrap-datetimepicker.min.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/js/owl-carousel/owl.carousel.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/lib.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/js/jquery-ui/jquery-ui.min.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/js/minicolors/miniColors.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/so_searchpro.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/so_megamenu.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/so-categories.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/so-listing-tabs.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/themecss/so-newletter-popup.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/footer/footer1.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/header/header1.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/theme.css') }}" rel="stylesheet">
    <link href="{{ asset('emarket/css/responsive.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hs-shop-home.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,400i,500,500i,700" rel="stylesheet">
    @stack('head')
</head>
<body class="common-home res layout-1">
    <div id="wrapper" class="wrapper-fluid banners-effect-5">
        @include('storefront.partials.header')

        @yield('content')

        @include('storefront.partials.footer')
    </div>

    <script src="{{ asset('emarket/js/jquery-2.2.4.min.js') }}"></script>
    <script src="{{ asset('emarket/js/owl-carousel/owl.carousel.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/libs.js') }}"></script>
    <script src="{{ asset('emarket/js/unveil/jquery.unveil.js') }}"></script>
    <script src="{{ asset('emarket/js/countdown/jquery.countdown.min.js') }}"></script>
    <script src="{{ asset('emarket/js/dcjqaccordion/jquery.dcjqaccordion.2.8.min.js') }}"></script>
    <script src="{{ asset('emarket/js/datetimepicker/moment.js') }}"></script>
    <script src="{{ asset('emarket/js/datetimepicker/bootstrap-datetimepicker.min.js') }}"></script>
    <script src="{{ asset('emarket/js/jquery-ui/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('emarket/js/modernizr/modernizr-2.6.2.min.js') }}"></script>
    <script src="{{ asset('emarket/js/minicolors/jquery.miniColors.min.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/application.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/homepage.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/toppanel.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/so_megamenu.js') }}"></script>
    <script src="{{ asset('emarket/js/themejs/addtocart.js') }}"></script>
    @vite('resources/js/storefront-bootstrap5.js')
    @stack('scripts')
</body>
</html>
