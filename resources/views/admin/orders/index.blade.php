@extends('admin.layouts.app')

@section('title', __('admin.orders.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
            <h1>{{ __('admin.orders.title') }}</h1>
            <p class="page-description">{{ __('admin.orders.description') }}</p>
        </div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.orders.create-social') }}">{{ __('admin.social_orders.create_title') }}</a>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-3 align-items-end mb-4">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="order-search">{{ __('admin.orders.search') }}</label>
                <input class="form-control" id="order-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.orders.search_hint') }}">
            </div>
            @foreach ([['status', 'order_status', $orderStatuses], ['channel', 'channel', $channels], ['shipment_status', 'shipment_status', $shipmentStatuses]] as [$field, $label, $options])
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="order-filter-{{ $field }}">{{ __('admin.orders.'.$label) }}</label>
                    <select class="form-select" id="order-filter-{{ $field }}" name="{{ $field }}">
                        <option value="">—</option>
                        @foreach ($options as $option)
                            <option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>
                                @if ($field === 'channel'){{ __('admin.orders.channel_values.'.$option) !== 'admin.orders.channel_values.'.$option ? __('admin.orders.channel_values.'.$option) : $option }}@else{{ $option }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary primary-button" type="submit">{{ __('admin.orders.filter') }}</button>
                <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.index') }}">{{ __('admin.orders.reset') }}</a>
            </div>
        </form>

        @if ($orders->isEmpty())
            <div class="empty-state"><p>{{ __('admin.orders.empty') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead><tr>
                        <th>{{ __('admin.orders.order_number') }}</th><th>{{ __('admin.orders.customer') }}</th><th>{{ __('admin.orders.channel') }}</th>
                        <th>{{ __('admin.orders.order_status') }}</th><th>{{ __('admin.orders.shipment_status') }}</th>
                        <th>{{ __('admin.orders.amount') }}</th><th>{{ __('admin.orders.placed_at') }}</th><th>{{ __('admin.orders.actions') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="order-number" dir="ltr">{{ $order->order_number }}</td>
                                <td>{{ $order->customer_name }}<small class="d-block text-secondary" dir="ltr">{{ $order->customer_phone }}</small></td>
                                <td>{{ __('admin.orders.channel_values.'.$order->channel) !== 'admin.orders.channel_values.'.$order->channel ? __('admin.orders.channel_values.'.$order->channel) : $order->channel }}</td>
                                <td>{{ __('admin.order_status.'.$order->status) !== 'admin.order_status.'.$order->status ? __('admin.order_status.'.$order->status) : $order->status }}@if ($order->close_outcome)<small class="d-block text-secondary">{{ __('admin.orders.outcomes.'.$order->close_outcome) }}@if ($order->closure_reason_code) · {{ __('admin.orders.failure_reasons.'.$order->closure_reason_code) }}@endif</small>@endif</td>
                                <td>{{ $order->shipment_status ?? '—' }}</td>
                                <td dir="ltr">{{ $order->currency }} {{ $order->grand_total }}</td>
                                <td dir="ltr">{{ \Illuminate\Support\Carbon::parse($order->placed_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</td>
                                <td><a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.orders.show', $order->id) }}">{{ __('admin.orders.view') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
