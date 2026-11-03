@extends('storefront.layouts.rapidshop')

@section('title', __('store.customer_account.addresses').' | '.__('store.brand'))

@section('content')
    <section class="hs-account-page">
        <div class="container hs-shell">
            <div class="hs-account-card hs-account-card-wide">
                <a class="small text-secondary" href="{{ route('store.account.index') }}">{{ __('store.customer_account.back_to_account') }}</a>
                <h1 class="mt-3">{{ $address->exists ? __('store.customer_account.edit_address') : __('store.customer_account.add_address') }}</h1>
                <p class="hs-account-intro">{{ __('store.customer_account.address_form_description') }}</p>

                <form method="POST" action="{{ $address->exists ? route('store.account.addresses.update', $address) : route('store.account.addresses.store') }}" class="hs-account-form">
                    @csrf
                    @if ($address->exists) @method('PUT') @endif
                    <div class="row g-3">
                        @foreach ([
                            'label' => ['optional' => true, 'maxlength' => 80],
                            'recipient_name' => ['required' => true, 'maxlength' => 160],
                            'phone' => ['required' => true, 'maxlength' => 32],
                            'country_code' => ['required' => true, 'maxlength' => 2],
                            'governorate' => ['required' => true, 'maxlength' => 120],
                            'city' => ['required' => true, 'maxlength' => 120],
                            'district' => ['optional' => true, 'maxlength' => 120],
                            'street' => ['required' => true, 'maxlength' => 255],
                            'building' => ['optional' => true, 'maxlength' => 80],
                            'floor' => ['optional' => true, 'maxlength' => 80],
                            'apartment' => ['optional' => true, 'maxlength' => 80],
                            'landmark' => ['optional' => true, 'maxlength' => 255],
                            'postal_code' => ['optional' => true, 'maxlength' => 20],
                        ] as $field => $options)
                            <div class="col-md-6">
                                <label class="form-label" for="address-{{ $field }}">{{ __('store.customer_account.fields.'.$field) }} @if (isset($options['required']))<span class="text-danger">*</span>@else <small class="text-secondary">({{ __('store.customer_account.optional') }})</small>@endif</label>
                                <input id="address-{{ $field }}" class="form-control hs-contact-control @error($field) is-invalid @enderror" name="{{ $field }}" value="{{ old($field, $address->{$field}) }}" maxlength="{{ $options['maxlength'] }}" @if ($field === 'phone') type="tel" dir="ltr" autocomplete="tel" @endif @if ($field === 'country_code') dir="ltr" @endif @if (isset($options['required'])) required @endif>
                                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="col-12">
                            <div class="form-check">
                                <input id="address-default" class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default))>
                                <label class="form-check-label" for="address-default">{{ __('store.customer_account.set_default_address') }}</label>
                            </div>
                            @error('is_default')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn hs-button hs-button-dark" type="submit">{{ __('store.customer_account.save_address') }}</button>
                        <a class="btn btn-outline-secondary" href="{{ route('store.account.index') }}">{{ __('store.customer_account.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
