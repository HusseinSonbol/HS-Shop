@extends('admin.layouts.app')

@section('title', $customer->display_name.' · '.__('admin.customers.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow" dir="ltr">{{ $customer->customer_number }}</p>
            <h1>{{ $customer->display_name }}</h1>
            <p class="page-description" dir="ltr">{{ $customer->phone }}{{ $customer->email ? ' · '.$customer->email : '' }}</p>
        </div>
        @if ($customer->trashed())
            <form method="POST" action="{{ route('admin.customers.restore', $customer) }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary primary-button customer-action-button" type="submit">{{ __('admin.customers.restore') }}</button>
            </form>
        @else
            <div class="customer-header-actions">
                <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.customers.edit', $customer) }}">{{ __('admin.customers.edit') }}</a>
                <form method="POST" action="{{ route('admin.customers.archive', $customer) }}" onsubmit="return confirm(@js(__('admin.customers.confirm_archive')))">
                    @csrf
                    @method('DELETE')
                    <button class="text-button text-danger" type="submit">{{ __('admin.customers.archive') }}</button>
                </form>
            </div>
        @endif
    </section>

    @if ($customer->trashed())
        <div class="flash-message warning-message" role="status">{{ __('admin.customers.archived_notice') }}</div>
    @endif

    <section class="customer-summary-grid">
        <article class="card panel customer-summary-card">
            <p class="eyebrow">{{ __('admin.customers.account') }}</p>
            <strong>{{ $customer->user ? __('admin.customers.account_linked') : __('admin.customers.account_not_linked') }}</strong>
            @if ($customer->user)<span dir="ltr">{{ $customer->user->email }}</span>@endif
        </article>
        <article class="card panel customer-summary-card">
            <p class="eyebrow">{{ __('admin.customers.orders') }}</p>
            <strong>{{ number_format($customer->orders()->count()) }}</strong>
        </article>
        <article class="card panel customer-summary-card">
            <p class="eyebrow">{{ __('admin.customers.marketing_consent') }}</p>
            <strong>{{ $customer->marketing_consent_at ? __('admin.customers.consent_yes') : __('admin.customers.consent_no') }}</strong>
        </article>
    </section>

    @if ($customer->notes)
        <section class="card panel customer-notes-panel">
            <h2>{{ __('admin.customers.internal_notes') }}</h2>
            <p>{{ $customer->notes }}</p>
        </section>
    @endif

    <section class="card panel customer-section-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">{{ __('admin.customers.contact') }}</p><h2>{{ __('admin.customers.addresses') }}</h2></div>
            @unless ($customer->trashed())
                <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.customers.addresses.create', $customer) }}">{{ __('admin.customers.add_address') }}</a>
            @endunless
        </div>
        @if ($customer->addresses->isEmpty())
            <div class="empty-state"><p>{{ __('admin.customers.no_addresses') }}</p></div>
        @else
            <div class="customer-address-grid">
                @foreach ($customer->addresses as $address)
                    <article class="card customer-address-card">
                        <div class="address-card-heading">
                            <h3>{{ $address->label ?: __('admin.customers.address') }}</h3>
                            @if ($address->is_default)<span class="status-pill">{{ __('admin.customers.default_address') }}</span>@endif
                        </div>
                        <p>{{ $address->recipient_name }} · <span dir="ltr">{{ $address->phone }}</span></p>
                        <p>{{ collect([$address->street, $address->building, $address->floor, $address->apartment, $address->district, $address->city, $address->governorate])->filter()->implode('، ') }}</p>
                        @if ($address->landmark)<p>{{ __('admin.customers.landmark') }}: {{ $address->landmark }}</p>@endif
                        @unless ($customer->trashed())
                            <div class="staff-actions">
                                <a class="text-button" href="{{ route('admin.customers.addresses.edit', [$customer, $address]) }}">{{ __('admin.customers.edit') }}</a>
                                <form method="POST" action="{{ route('admin.customers.addresses.archive', [$customer, $address]) }}" onsubmit="return confirm(@js(__('admin.customers.confirm_address_archive')))">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-button text-danger" type="submit">{{ __('admin.customers.archive') }}</button>
                                </form>
                            </div>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card panel customer-section-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">{{ __('admin.customers.history') }}</p><h2>{{ __('admin.customers.orders') }}</h2></div>
            <span class="panel-count">{{ number_format($orders->total()) }}</span>
        </div>
        @if ($orders->isEmpty())
            <div class="empty-state"><p>{{ __('admin.customers.no_orders') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead><tr><th>{{ __('admin.dashboard.order_number') }}</th><th>{{ __('admin.dashboard.status') }}</th><th>{{ __('admin.dashboard.total') }}</th><th>{{ __('admin.dashboard.date') }}</th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="order-number">{{ $order->order_number }}</td>
                                <td>{{ __('admin.order_status.'.$order->status) }}</td>
                                <td dir="ltr">{{ $order->currency }} {{ $order->grand_total }}</td>
                                <td dir="ltr">{{ $order->placed_at?->setTimezone('Africa/Cairo')->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
