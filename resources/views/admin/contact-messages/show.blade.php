@extends('admin.layouts.app')

@section('title', __('admin.contact_messages.message_details'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div><p class="eyebrow">{{ __('admin.contact_messages.title') }}</p><h1>{{ $contactMessage->subject ?: __('admin.contact_messages.no_subject') }}</h1><p class="page-description">{{ __('admin.contact_messages.received_at') }}: {{ $contactMessage->received_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.contact-messages.index') }}">{{ __('admin.contact_messages.back') }}</a>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card panel customer-list-panel p-3 p-md-4">
                <h2 class="h5">{{ __('admin.contact_messages.message') }}</h2>
                <div class="contact-message-body" dir="auto">{{ $contactMessage->message }}</div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card panel customer-list-panel p-3 p-md-4">
                <h2 class="h5">{{ __('admin.contact_messages.sender') }}</h2>
                <dl class="mb-4">
                    <dt>{{ __('admin.contact_messages.name') }}</dt><dd>{{ $contactMessage->sender_name }}</dd>
                    <dt>{{ __('admin.contact_messages.email') }}</dt><dd dir="ltr" class="text-start"><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></dd>
                    @if ($contactMessage->phone)<dt>{{ __('admin.contact_messages.phone') }}</dt><dd dir="ltr" class="text-start"><a href="tel:{{ $contactMessage->phone }}">{{ $contactMessage->phone }}</a></dd>@endif
                </dl>
                <form method="POST" action="{{ route('admin.contact-messages.status', $contactMessage) }}" class="d-grid gap-3">
                    @csrf @method('PATCH')
                    <div><label for="status" class="form-label">{{ __('admin.contact_messages.status') }}</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>@foreach (['read', 'closed', 'spam'] as $status)<option value="{{ $status }}" @selected(old('status', in_array($contactMessage->status, ['read', 'closed', 'spam'], true) ? $contactMessage->status : 'read') === $status)>{{ __('admin.contact_messages.statuses.'.$status) }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <button class="btn btn-primary primary-button" type="submit">{{ __('admin.contact_messages.save_status') }}</button>
                </form>
            </section>
        </div>
    </div>
@endsection
