@extends('storefront.layouts.rapidshop')

@section('title', __('store.tickets.notifications').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page"><div class="container hs-shell"><div class="hs-account-heading mb-4"><span class="hs-eyebrow">{{ __('store.customer_account.title') }}</span><h1>{{ __('store.tickets.notifications') }}</h1><a class="btn btn-outline-secondary mt-2" href="{{ route('store.account.tickets.index') }}">{{ __('store.tickets.title') }}</a></div><section class="hs-account-panel"><div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item d-flex justify-content-between align-items-center gap-3 flex-wrap {{ $notification->read_at ? '' : 'fw-semibold' }}"><div><p class="mb-1">{{ data_get($notification->data, 'summary', __('store.tickets.notification_new')) }}</p><small class="text-secondary" dir="ltr">{{ $notification->created_at }}</small></div><form method="POST" action="{{ route('store.account.notifications.read', $notification->id) }}">@csrf<button class="btn btn-outline-secondary" type="submit">{{ __('store.tickets.open_notification') }}</button></form></div>@empty<p class="text-secondary p-3">{{ __('store.tickets.no_notifications') }}</p>@endforelse{{ $notifications->links() }}</div></section></div></section>
@endsection
