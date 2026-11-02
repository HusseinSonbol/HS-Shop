@extends('admin.layouts.app')

@section('title', __('admin.contact_messages.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div><p class="eyebrow">{{ __('admin.admin_panel') }}</p><h1>{{ __('admin.contact_messages.title') }}</h1><p class="page-description">{{ __('admin.contact_messages.description') }}</p></div>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="customer-filters">
            <label for="contact-search">{{ __('admin.contact_messages.search') }}</label>
            <input class="form-control" id="contact-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.contact_messages.search_hint') }}">
            <select class="form-select" name="status" aria-label="{{ __('admin.contact_messages.status') }}">
                <option value="">{{ __('admin.contact_messages.all_statuses') }}</option>
                @foreach (['new', 'read', 'converted', 'closed', 'spam'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('admin.contact_messages.statuses.'.$status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.contact_messages.search') }}</button>
        </form>

        <div class="table-scroll">
            <table class="table table-hover align-middle orders-table mb-0">
                <thead><tr><th>{{ __('admin.contact_messages.sender') }}</th><th>{{ __('admin.contact_messages.subject') }}</th><th>{{ __('admin.contact_messages.received_at') }}</th><th>{{ __('admin.contact_messages.status') }}</th><th>{{ __('admin.contact_messages.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td><strong>{{ $message->sender_name }}</strong><div class="small text-body-secondary" dir="ltr">{{ $message->email }}</div></td>
                            <td>{{ $message->subject ?: __('admin.contact_messages.no_subject') }}</td>
                            <td>{{ $message->received_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td><span class="badge {{ $message->status === 'new' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ __('admin.contact_messages.statuses.'.$message->status) }}</span></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.contact-messages.show', $message) }}">{{ __('admin.contact_messages.view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4">{{ __('admin.contact_messages.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $messages->links() }}</div>
    </section>
@endsection
