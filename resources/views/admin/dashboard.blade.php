@extends('admin.layouts.app')

@section('title', __('admin.dashboard.title'))

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.dashboard.today') }} · {{ $todayLabel }}</p>
            <h1>{{ __('admin.dashboard.greeting', ['name' => auth()->user()->name]) }}</h1>
            <p class="page-description">{{ __('admin.dashboard.description') }}</p>
        </div>
        @if ($canViewReports)<a class="btn btn-primary primary-button" href="{{ route('admin.reports.index') }}">{{ __('admin.reports.navigation') }}</a>@endif
    </section>

    <section class="stats-grid" aria-label="{{ __('admin.dashboard.title') }}">
        @foreach ($statsCards as $index => $card)
            <a class="card stat-card {{ $index === 0 ? 'stat-card-navy' : '' }} text-decoration-none" href="{{ $card['url'] }}">
                <div class="stat-card-top"><span class="stat-icon">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span class="stat-dot"></span></div>
                <p>{{ $card['label'] }}</p>
                <strong>{{ number_format($card['count']) }}</strong>
            </a>
        @endforeach
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

    <div class="row g-4">
        @foreach ($queueSections as $queue)
            <div class="col-xl-6"><section class="card panel recent-orders-panel h-100"><div class="panel-heading"><div><p class="eyebrow">HS SHOP</p><h2>{{ $queue['title'] }}</h2></div><span class="panel-count">{{ number_format($queue['rows']->count()) }}</span></div>
                @forelse($queue['rows'] as $row)<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 border-top py-3 text-decoration-none" href="{{ $row['url'] }}"><span><strong class="d-block">{{ $row['title'] }}</strong><small class="text-secondary">{{ $row['subtitle'] }}</small></span><span class="badge text-bg-light">{{ $row['detail'] }}</span></a>@empty<div class="empty-state"><span class="empty-state-mark" aria-hidden="true">—</span><p>{{ __('admin.dashboard.queue_empty') }}</p></div>@endforelse
            </section></div>
        @endforeach
    </div>
@endsection
