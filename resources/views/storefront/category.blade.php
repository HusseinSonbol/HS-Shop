@extends('storefront.layouts.rapidshop')

@section('title', ($categoryName ?: __('store.categories.showing_all')).' | '.__('store.brand'))

@section('content')
    <section class="hs-category-breadcrumb">
        <div class="container hs-shell">
            <nav aria-label="{{ __('store.navigation.menu') }}">
                <ol class="hs-category-breadcrumb-list">
                    <li><a href="{{ route('store.home') }}">{{ __('store.navigation.home') }}</a></li>
                    <li aria-current="page">{{ $categoryName ?: __('store.categories.breadcrumb') }}</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="hs-catalog-page">
        <div class="container hs-shell">
            <div class="row g-4 g-xl-5">
                <aside class="col-lg-3">
                    <div class="hs-catalog-sidebar">
                        <div class="hs-catalog-sidebar-heading">
                            <span class="hs-eyebrow">HS Shop</span>
                            <h2>{{ __('store.categories.sidebar_title') }}</h2>
                        </div>
                        <nav aria-label="{{ __('store.categories.sidebar_title') }}">
                            <ul class="hs-category-tree">
                                <li>
                                    <a class="@if (! $category) is-current @endif" href="{{ route('store.shop') }}">{{ __('store.categories.showing_all') }}</a>
                                </li>
                                @foreach ($categoryTree as $treeItem)
                                    @include('storefront.partials.category-tree-item', ['treeItem' => $treeItem, 'currentSlug' => $category?->slug])
                                @endforeach
                            </ul>
                        </nav>
                    </div>
                </aside>

                <div class="col-lg-9">
                    @if ($category)
                        <header class="hs-category-hero @if (! $categoryImage) hs-category-hero--plain @endif">
                            @if ($categoryImage)
                                <img class="hs-category-hero-image" src="{{ $categoryImage }}" alt="" aria-hidden="true" fetchpriority="high">
                            @endif
                            <div class="hs-category-hero-copy">
                                <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.categories.eyebrow') }}</span>
                                <h1>{{ $categoryName }}</h1>
                                @if ($categoryDescription)
                                    <p>{{ $categoryDescription }}</p>
                                @endif
                            </div>
                        </header>
                    @else
                        <header class="hs-catalog-heading">
                            <span class="hs-eyebrow">{{ __('store.categories.eyebrow') }}</span>
                            <h1>{{ __('store.categories.showing_all') }}</h1>
                            <p>{{ __('store.categories.filter_title') }}</p>
                        </header>
                    @endif

                    @if ($childCategories->isNotEmpty())
                        <section class="hs-subcategory-section" aria-labelledby="hs-subcategory-title">
                            <h2 id="hs-subcategory-title">{{ __('store.categories.subcategories') }}</h2>
                            <div class="hs-subcategory-list">
                                @foreach ($childCategories as $childCategory)
                                    <a href="{{ route('store.category', $childCategory['slug']) }}">{{ $childCategory['name'] }}<span aria-hidden="true">↗</span></a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <div class="hs-catalog-toolbar">
                        <form class="hs-catalog-search" method="GET" action="{{ $category ? route('store.category', $category->slug) : route('store.shop') }}" role="search">
                            <label class="visually-hidden" for="catalog-search">{{ __('store.categories.search_label') }}</label>
                            <input id="catalog-search" class="form-control" type="search" name="q" value="{{ $search }}" placeholder="{{ __('store.categories.search_placeholder') }}" maxlength="120">
                            @if ($sort !== 'newest')<input type="hidden" name="sort" value="{{ $sort }}">@endif
                            <button class="btn hs-button hs-button-dark" type="submit">{{ __('store.categories.search_action') }}</button>
                        </form>
                        <label class="hs-catalog-sort" for="catalog-sort">
                            <span>{{ __('store.categories.sort_label') }}</span>
                            <select id="catalog-sort" class="form-select" name="sort" form="catalog-sort-form" onchange="this.form.submit()">
                                <option value="newest" @selected($sort === 'newest')>{{ __('store.categories.sort_newest') }}</option>
                                <option value="price_low" @selected($sort === 'price_low')>{{ __('store.categories.sort_price_low') }}</option>
                                <option value="price_high" @selected($sort === 'price_high')>{{ __('store.categories.sort_price_high') }}</option>
                                <option value="name" @selected($sort === 'name')>{{ __('store.categories.sort_name') }}</option>
                            </select>
                            <form id="catalog-sort-form" method="GET" action="{{ $category ? route('store.category', $category->slug) : route('store.shop') }}">
                                @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                            </form>
                        </label>
                    </div>

                    <div class="hs-catalog-results" aria-live="polite">
                        @if ($products->total() > 0)
                            {{ __('store.categories.results_count', ['first' => $products->firstItem(), 'last' => $products->lastItem(), 'total' => $products->total()]) }}
                        @else
                            {{ $search !== '' ? __('store.categories.empty_search') : __('store.categories.empty') }}
                        @endif
                    </div>

                    @if ($products->isNotEmpty())
                        <div class="row g-3 g-md-4 hs-catalog-products">
                            @foreach ($products as $product)
                                <div class="col-6 col-md-4">@include('storefront.partials.rapidshop-product-card', ['product' => $product])</div>
                            @endforeach
                        </div>
                        <nav class="hs-catalog-pagination" aria-label="{{ __('store.categories.pagination') }}">
                            {{ $products->links('pagination::bootstrap-5') }}
                        </nav>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
