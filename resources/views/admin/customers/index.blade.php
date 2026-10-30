@extends('admin.layouts.app')

@section('title', __('admin.customers.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
            <h1>{{ __('admin.customers.title') }}</h1>
            <p class="page-description">{{ __('admin.customers.description') }}</p>
        </div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.customers.create') }}">{{ __('admin.customers.create') }}</a>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="customer-filters">
            <label for="customer-search">{{ __('admin.customers.search') }}</label>
            <input class="form-control" id="customer-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.customers.search_hint') }}">
            <select class="form-select" name="scope" aria-label="{{ __('admin.customers.scope') }}">
                <option value="active" @selected(($filters['scope'] ?? 'active') === 'active')>{{ __('admin.customers.active_list') }}</option>
                <option value="archived" @selected(($filters['scope'] ?? '') === 'archived')>{{ __('admin.customers.archived_list') }}</option>
            </select>
            <button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.customers.search_button') }}</button>
        </form>

        @if ($customers->isEmpty())
            <div class="empty-state"><p>{{ __('admin.customers.empty') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.customers.number') }}</th>
                            <th>{{ __('admin.customers.name') }}</th>
                            <th>{{ __('admin.customers.phone') }}</th>
                            <th>{{ __('admin.customers.email') }}</th>
                            <th>{{ __('admin.customers.orders') }}</th>
                            <th>{{ __('admin.customers.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td class="order-number" dir="ltr">{{ $customer->customer_number }}</td>
                                <td>{{ $customer->display_name }}</td>
                                <td dir="ltr">{{ $customer->phone }}</td>
                                <td dir="ltr">{{ $customer->email ?: '—' }}</td>
                                <td>{{ number_format($customer->orders_count) }}</td>
                                <td><a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.customers.show', $customer) }}">{{ __('admin.customers.view') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $customers->links() }}</div>
        @endif
    </section>
@endsection
