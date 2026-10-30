@extends('admin.layouts.app')

@php($isEditing = $customer->exists)

@section('title', $isEditing ? __('admin.customers.edit_title') : __('admin.customers.create_title'))

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow"><a href="{{ route('admin.customers.index') }}">{{ __('admin.customers.title') }}</a></p>
            <h1>{{ $isEditing ? __('admin.customers.edit_title') : __('admin.customers.create_title') }}</h1>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger flash-message error-message" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card panel form-panel">
        <form method="POST" action="{{ $isEditing ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" class="admin-form">
            @csrf
            @if ($isEditing) @method('PUT') @endif

            <label class="form-label">{{ __('admin.customers.name') }}
                <input class="form-control" name="display_name" value="{{ old('display_name', $customer->display_name) }}" required maxlength="160" autocomplete="name">
            </label>
            <label class="form-label">{{ __('admin.customers.phone') }}
                <input class="form-control" name="phone" value="{{ old('phone', $customer->phone) }}" required maxlength="32" dir="ltr" inputmode="tel" autocomplete="tel">
            </label>
            <label class="form-label">{{ __('admin.customers.email') }}
                <input class="form-control" type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="190" dir="ltr" autocomplete="email">
            </label>
            <label class="form-label full-width-field">{{ __('admin.customers.internal_notes') }}
                <textarea class="form-control" name="notes" rows="5" maxlength="5000">{{ old('notes', $customer->notes) }}</textarea>
            </label>
            <p class="form-note full-width-field">{{ __('admin.customers.phone_hint') }}</p>
            <div class="form-actions">
                <a class="text-button" href="{{ $isEditing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}">{{ __('admin.cancel') }}</a>
                <button class="btn btn-primary primary-button" type="submit">{{ __('admin.customers.save') }}</button>
            </div>
        </form>
    </section>
@endsection
