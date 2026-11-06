@extends('admin.layouts.app')

@section('title', __('admin.support_tickets.title'))

@section('content')
    <section class="page-heading staff-page-heading"><div><p class="eyebrow">{{ __('admin.support_tickets.navigation') }}</p><h1>{{ __('admin.support_tickets.title') }}</h1><p class="page-description">{{ __('admin.support_tickets.description') }}</p></div></section>
    <section class="card panel customer-list-panel p-3 p-md-4">
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-6"><label class="visually-hidden" for="ticket-q">{{ __('admin.support_tickets.search') }}</label><input id="ticket-q" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.support_tickets.search') }}"></div>
            <div class="col-md-4"><label class="visually-hidden" for="ticket-status">{{ __('admin.support_tickets.status') }}</label><select id="ticket-status" class="form-select" name="status"><option value="">{{ __('admin.support_tickets.all_statuses') }}</option>@foreach (['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('admin.support_tickets.statuses.'.$status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary primary-button w-100" type="submit">{{ __('admin.support_tickets.search') }}</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ __('admin.support_tickets.number') }}</th><th>{{ __('admin.support_tickets.subject') }}</th><th>{{ __('admin.support_tickets.requester') }}</th><th>{{ __('admin.support_tickets.status') }}</th><th>{{ __('admin.support_tickets.assignee') }}</th><th>{{ __('admin.support_tickets.updated_at') }}</th><th></th></tr></thead><tbody>
        @forelse($tickets as $ticket)<tr><td dir="ltr">{{ $ticket->ticket_number }}</td><td>{{ $ticket->subject }}</td><td>{{ $ticket->customer_name ?: $ticket->requester_name }}</td><td><span class="badge text-bg-light">{{ __('admin.support_tickets.statuses.'.$ticket->status) }}</span></td><td>{{ $ticket->assignee_name ?: '—' }}</td><td dir="ltr">{{ $ticket->updated_at }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.support-tickets.show', $ticket->id) }}">{{ __('admin.support_tickets.view') }}</a></td></tr>
        @empty<tr><td colspan="7" class="text-center py-4">{{ __('admin.support_tickets.empty') }}</td></tr>@endforelse
        </tbody></table></div>{{ $tickets->links() }}
    </section>
@endsection
