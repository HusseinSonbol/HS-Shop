@extends('admin.layouts.app')

@section('title', __('admin.returns.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div><p class="eyebrow">HS Shop</p><h1>{{ __('admin.returns.title') }}</h1></div>
        <a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.index') }}">{{ __('admin.returns.open_order') }}</a>
    </section>
    <section class="card panel customer-section-panel">
        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-5"><label for="return-status" class="form-label">{{ __('admin.returns.filter_status') }}</label><select class="form-select" id="return-status" name="status"><option value="">{{ __('admin.returns.all_statuses') }}</option>@foreach (\App\Support\ReturnCaseLifecycle::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('admin.returns.statuses.'.$status) }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.orders.search') }}</button></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>{{ __('admin.returns.number') }}</th><th>{{ __('admin.returns.order') }}</th><th>{{ __('admin.returns.customer') }}</th><th>{{ __('admin.returns.reason') }}</th><th>{{ __('admin.returns.case_status') }}</th><th>{{ __('admin.returns.requested_at') }}</th><th></th></tr></thead><tbody>
        @forelse ($cases as $case)
            <tr><td dir="ltr">{{ $case->return_number }}</td><td>{{ $case->order_number }}</td><td>{{ $case->customer_name }}<small class="d-block" dir="ltr">{{ $case->phone }}</small></td><td>{{ __('admin.returns.'.$case->reason_code) }}</td><td><span class="badge text-bg-secondary">{{ __('admin.returns.statuses.'.$case->status) }}</span></td><td dir="ltr">{{ \Illuminate\Support\Carbon::parse($case->requested_at)->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</td><td><a class="btn btn-sm btn-primary primary-button" href="{{ route('admin.returns.show', $case->id) }}">{{ __('admin.returns.view') }}</a></td></tr>
        @empty
            <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('admin.returns.no_cases') }}</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $cases->links() }}
    </section>
@endsection
