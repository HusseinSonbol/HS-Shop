@extends('admin.layouts.app')

@php($isEditing = $address->exists)

@section('title', $isEditing ? __('admin.customers.edit_address') : __('admin.customers.add_address'))

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow"><a href="{{ route('admin.customers.show', $customer) }}">{{ $customer->display_name }}</a></p>
            <h1>{{ $isEditing ? __('admin.customers.edit_address') : __('admin.customers.add_address') }}</h1>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger flash-message error-message" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card panel form-panel">
        <form method="POST" action="{{ $isEditing ? route('admin.customers.addresses.update', [$customer, $address]) : route('admin.customers.addresses.store', $customer) }}" class="admin-form">
            @csrf
            @if ($isEditing) @method('PUT') @endif

            <label class="form-label">{{ __('admin.customers.address_label') }}
                <input class="form-control" name="label" value="{{ old('label', $address->label) }}" maxlength="80" placeholder="{{ __('admin.customers.address_label_hint') }}">
            </label>
            <label class="form-label">{{ __('admin.customers.recipient_name') }}
                <input class="form-control" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" required maxlength="160" autocomplete="name">
            </label>
            <label class="form-label">{{ __('admin.customers.phone') }}
                <input class="form-control" name="phone" value="{{ old('phone', $address->phone) }}" required maxlength="32" dir="ltr" inputmode="tel" autocomplete="tel">
            </label>
            <label class="form-label">{{ __('admin.customers.country_code') }}
                <input class="form-control" name="country_code" value="{{ old('country_code', $address->country_code ?: 'EG') }}" required maxlength="2" minlength="2" dir="ltr" autocapitalize="characters">
            </label>
            <label class="form-label">{{ __('admin.customers.governorate') }}
                <input class="form-control" name="governorate" value="{{ old('governorate', $address->governorate) }}" required maxlength="120" autocomplete="address-level1">
            </label>
            <label class="form-label">{{ __('admin.customers.city') }}
                <input class="form-control" name="city" value="{{ old('city', $address->city) }}" required maxlength="120" autocomplete="address-level2">
            </label>
            <label class="form-label">{{ __('admin.customers.district') }}
                <input class="form-control" name="district" value="{{ old('district', $address->district) }}" maxlength="120">
            </label>
            <label class="form-label">{{ __('admin.customers.street') }}
                <input class="form-control" name="street" value="{{ old('street', $address->street) }}" required maxlength="255" autocomplete="street-address">
            </label>
            <label class="form-label">{{ __('admin.customers.building') }}
                <input class="form-control" name="building" value="{{ old('building', $address->building) }}" maxlength="80">
            </label>
            <label class="form-label">{{ __('admin.customers.floor') }}
                <input class="form-control" name="floor" value="{{ old('floor', $address->floor) }}" maxlength="80">
            </label>
            <label class="form-label">{{ __('admin.customers.apartment') }}
                <input class="form-control" name="apartment" value="{{ old('apartment', $address->apartment) }}" maxlength="80">
            </label>
            <label class="form-label">{{ __('admin.customers.postal_code') }}
                <input class="form-control" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}" maxlength="20" dir="ltr" autocomplete="postal-code">
            </label>
            <label class="form-label full-width-field">{{ __('admin.customers.landmark') }}
                <input class="form-control" name="landmark" value="{{ old('landmark', $address->landmark) }}" maxlength="255">
            </label>
            <label class="checkbox-field form-check full-width-field">
                <input type="hidden" name="is_default" value="0">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->exists ? $address->is_default : false) == true)>
                {{ __('admin.customers.default_address') }}
            </label>
            <div class="form-actions">
                <a class="text-button" href="{{ route('admin.customers.show', $customer) }}">{{ __('admin.cancel') }}</a>
                <button class="btn btn-primary primary-button" type="submit">{{ __('admin.customers.save_address') }}</button>
            </div>
        </form>
    </section>
@endsection
