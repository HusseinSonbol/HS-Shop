@extends('admin.layouts.app')

@section('title', __('admin.notifications.title'))

@section('content')
    <section class="page-heading staff-page-heading"><div><p class="eyebrow">{{ __('admin.notifications.title') }}</p><h1>{{ __('admin.notifications.title') }}</h1><p class="page-description">{{ __('admin.notifications.description') }}</p></div></section>
    <section class="card panel customer-list-panel p-3 p-md-4"><div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item d-flex justify-content-between align-items-center gap-3 flex-wrap {{ $notification->read_at ? '' : 'fw-semibold' }}"><div><p class="mb-1">{{ data_get($notification->data, 'summary', __('admin.notifications.new_notice')) }}</p><small class="text-secondary" dir="ltr">{{ $notification->created_at }}</small></div><form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">{{ $notification->read_at ? __('admin.notifications.open') : __('admin.notifications.open_mark_read') }}</button></form></div>@empty<p class="text-secondary p-3">{{ __('admin.notifications.empty') }}</p>@endforelse</div>{{ $notifications->links() }}</section>
@endsection
