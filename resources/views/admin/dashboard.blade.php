@extends('admin.layouts.app')

@section('title', __('admin.dashboard.title'))

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.dashboard.today') }} · {{ $todayLabel }}</p>
            <h1>{{ __('admin.dashboard.greeting', ['name' => auth()->user()->name]) }}</h1>
            <p class="page-description">{{ __('admin.dashboard.description') }}</p>
        </div>
        <span class="heading-accent" aria-hidden="true"></span>
    </section>

    <section class="stats-grid" aria-label="{{ __('admin.dashboard.title') }}">
        <article class="card stat-card stat-card-navy">
            <div class="stat-card-top"><span class="stat-icon">01</span><span class="stat-dot"></span></div>
            <p>{{ __('admin.dashboard.orders_today') }}</p>
            <strong>{{ number_format($stats['orders_today']) }}</strong>
        </article>
        <article class="card stat-card">
            <div class="stat-card-top"><span class="stat-icon">02</span><span class="stat-dot"></span></div>
            <p>{{ __('admin.dashboard.available_products') }}</p>
            <strong>{{ number_format($stats['available_products']) }}</strong>
        </article>
        <article class="card stat-card">
            <div class="stat-card-top"><span class="stat-icon">03</span><span class="stat-dot"></span></div>
            <p>{{ __('admin.dashboard.pending_reviews') }}</p>
            <strong>{{ number_format($stats['pending_reviews']) }}</strong>
        </article>
        <article class="card stat-card">
            <div class="stat-card-top"><span class="stat-icon">04</span><span class="stat-dot"></span></div>
            <p>{{ __('admin.dashboard.active_tickets') }}</p>
            <strong>{{ number_format($stats['active_tickets']) }}</strong>
        </article>
    </section>

    @if ($moduleSummaries !== [])
        <section class="module-summary-section" aria-labelledby="module-summary-title">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
                    <h2 id="module-summary-title">{{ __('admin.dashboard.module_summary') }}</h2>
                </div>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-3">
                @foreach ($moduleSummaries as $module)
                    <div class="col">
                        <a class="card module-summary-card h-100" href="{{ $module['url'] }}">
                            <span>{{ $module['label'] }}</span>
                            <strong>{{ number_format($module['count']) }}</strong>
                            <small>{{ __('admin.dashboard.open_module') }} <span aria-hidden="true">↗</span></small>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card panel recent-orders-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">HS SHOP</p>
                <h2>{{ __('admin.dashboard.recent_orders') }}</h2>
            </div>
            <span class="panel-count">{{ number_format($recentOrders->count()) }}</span>
        </div>

        @if ($recentOrders->isEmpty())
            <div class="empty-state">
                <span class="empty-state-mark" aria-hidden="true">—</span>
                <p>{{ __('admin.dashboard.no_orders') }}</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.dashboard.order_number') }}</th>
                            <th>{{ __('admin.dashboard.customer') }}</th>
                            <th>{{ __('admin.dashboard.status') }}</th>
                            <th>{{ __('admin.dashboard.total') }}</th>
                            <th>{{ __('admin.dashboard.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentOrders as $order)
                            @php($statusKey = 'admin.order_status.'.$order->status)
                            <tr>
                                <td class="order-number">{{ $order->order_number }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td><span class="status-pill">{{ __($statusKey) === $statusKey ? str_replace('_', ' ', ucfirst($order->status)) : __($statusKey) }}</span></td>
                                <td dir="ltr" class="amount-cell">{{ $order->currency }} {{ $order->grand_total }}</td>
                                <td dir="ltr" class="date-cell">{{ \Carbon\CarbonImmutable::parse($order->placed_at, 'UTC')->setTimezone('Africa/Cairo')->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
