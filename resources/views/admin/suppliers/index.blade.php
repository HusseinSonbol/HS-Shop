@extends('admin.layouts.app')

@section('title', __('admin.suppliers.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
            <h1>{{ __('admin.suppliers.title') }}</h1>
            <p class="page-description">{{ __('admin.suppliers.page_description') }}</p>
        </div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.suppliers.create') }}">{{ __('admin.suppliers.create') }}</a>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.suppliers.index') }}" class="customer-filters">
            <label for="supplier-search">{{ __('admin.suppliers.search') }}</label>
            <input class="form-control" id="supplier-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.suppliers.search_hint') }}">
            <select class="form-select" id="supplier-scope" name="scope" aria-label="{{ __('admin.suppliers.scope') }}">
                <option value="active" @selected($filters['scope'] === 'active')>{{ __('admin.suppliers.active_scope') }}</option>
                <option value="archived" @selected($filters['scope'] === 'archived')>{{ __('admin.suppliers.archived_scope') }}</option>
            </select>
            <button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.suppliers.search') }}</button>
        </form>

        @if ($suppliers->isEmpty())
            <div class="empty-state"><p>{{ __('admin.suppliers.empty') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead>
                        <tr><th>{{ __('admin.suppliers.code') }}</th><th>{{ __('admin.suppliers.name') }}</th><th>{{ __('admin.suppliers.type') }}</th><th>{{ __('admin.suppliers.owner_mobile') }}</th><th>{{ __('admin.suppliers.offers_count') }}</th><th>{{ __('admin.suppliers.actions') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td class="order-number" dir="ltr">{{ $supplier->code }}</td>
                                <td>{{ $supplier->name }}</td>
                                <td>{{ __('admin.suppliers.types.'.$supplier->supplier_type) }}</td>
                                <td dir="ltr">{{ $supplier->owner_phone ?: '—' }}</td>
                                <td>{{ number_format($supplier->product_suppliers_count) }}</td>
                                <td>
                                    <div class="staff-actions">
                                        @if ($supplier->trashed())
                                            <form method="POST" action="{{ route('admin.suppliers.restore', $supplier) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-link text-button p-0" type="submit">{{ __('admin.suppliers.restore') }}</button></form>
                                        @else
                                            <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.suppliers.show', $supplier) }}">{{ __('admin.suppliers.view') }}</a>
                                            <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.suppliers.edit', $supplier) }}">{{ __('admin.suppliers.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.suppliers.archive', $supplier) }}" onsubmit="return confirm(@js(__('admin.suppliers.confirm_archive')))">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-button text-danger p-0" type="submit">{{ __('admin.suppliers.archive') }}</button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $suppliers->links() }}</div>
        @endif
    </section>
@endsection
