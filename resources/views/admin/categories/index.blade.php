@extends('admin.layouts.app')

@section('title', __('admin.categories.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
            <h1>{{ __('admin.categories.title') }}</h1>
            <p class="page-description">{{ __('admin.categories.page_description') }}</p>
        </div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.categories.create') }}">{{ __('admin.categories.create') }}</a>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="customer-filters">
            <label for="category-search">{{ __('admin.categories.search') }}</label>
            <input class="form-control" id="category-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.categories.search_hint') }}">
            <select class="form-select" id="category-scope" name="scope" aria-label="{{ __('admin.categories.scope') }}">
                <option value="active" @selected($filters['scope'] === 'active')>{{ __('admin.categories.active_scope') }}</option>
                <option value="archived" @selected($filters['scope'] === 'archived')>{{ __('admin.categories.archived_scope') }}</option>
            </select>
            <button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.categories.search') }}</button>
        </form>

        @if ($categories->isEmpty())
            <div class="empty-state"><p>{{ __('admin.categories.empty') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead><tr><th>{{ __('admin.categories.name') }}</th><th>{{ __('admin.categories.slug') }}</th><th>{{ __('admin.categories.order') }}</th><th>{{ __('admin.categories.status') }}</th><th>{{ __('admin.categories.actions') }}</th></tr></thead>
                    <tbody>
                        @foreach ($categories as $category)
                            @include('admin.categories.partials.row', ['category' => $category, 'depth' => 0])
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
