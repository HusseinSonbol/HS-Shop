@extends('storefront.layouts.rapidshop')

@section('title', __('store.tickets.title').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page"><div class="container hs-shell"><div class="hs-account-heading mb-4"><span class="hs-eyebrow">{{ __('store.customer_account.title') }}</span><h1>{{ __('store.tickets.title') }}</h1><div class="d-flex gap-2 flex-wrap mt-3"><a class="btn hs-button hs-button-dark" href="{{ route('store.account.tickets.create') }}">{{ __('store.tickets.new_ticket') }}</a><a class="btn btn-outline-secondary" href="{{ route('store.account.notifications.index') }}">{{ __('store.tickets.notifications') }}</a><a class="btn btn-outline-secondary" href="{{ route('store.account.index') }}">{{ __('store.customer_account.title') }}</a></div></div>
        <section class="hs-account-panel">@forelse($tickets as $ticket)<article class="hs-account-address d-flex justify-content-between gap-3 flex-wrap mb-3"><div><small dir="ltr">{{ $ticket->ticket_number }}</small><h2 class="h5 mt-1">{{ $ticket->subject }}</h2><span class="badge text-bg-light">{{ __('store.tickets.statuses.'.$ticket->status) }}</span><small class="d-block text-secondary mt-2">{{ __('store.tickets.updated') }}: {{ $ticket->updated_at }}</small></div><a class="btn hs-button hs-button-dark align-self-center" href="{{ route('store.account.tickets.show', $ticket->id) }}">{{ __('store.tickets.view') }}</a></article>@empty<div class="hs-account-empty">{{ __('store.tickets.empty') }}</div>@endforelse{{ $tickets->links() }}</section>
    </div></section>
@endsection
