@extends('admin.layouts.app')

@php($isEditing = $staffMember->exists)

@section('title', $isEditing ? __('admin.staff.edit_title') : __('admin.staff.create_title'))

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow"><a href="{{ route('admin.staff.index') }}">{{ __('admin.staff.title') }}</a></p>
            <h1>{{ $isEditing ? __('admin.staff.edit_title') : __('admin.staff.create_title') }}</h1>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger flash-message error-message" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card panel form-panel">
        <form method="POST" action="{{ $isEditing ? route('admin.staff.update', $staffMember) : route('admin.staff.store') }}" class="admin-form">
            @csrf
            @if ($isEditing) @method('PUT') @endif

            <label class="form-label">{{ __('admin.staff.name') }}
                <input class="form-control" name="name" value="{{ old('name', $staffMember->name) }}" required maxlength="255" autocomplete="name">
            </label>
            <label class="form-label">{{ __('admin.staff.email') }}
                <input class="form-control" type="email" name="email" value="{{ old('email', $staffMember->email) }}" required maxlength="190" dir="ltr" autocomplete="email">
            </label>
            <label class="form-label">{{ __('admin.staff.phone') }}
                <input class="form-control" name="phone" value="{{ old('phone', $staffMember->phone) }}" maxlength="32" dir="ltr" autocomplete="tel">
            </label>
            <label class="form-label">{{ __('admin.staff.password') }}
                <input class="form-control" type="password" name="password" {{ $isEditing ? '' : 'required' }} minlength="9" maxlength="1024" autocomplete="new-password">
                @if ($isEditing)<small>{{ __('admin.staff.password_hint') }}</small>@endif
            </label>
            <label class="form-label">{{ __('admin.staff.role') }}
                <select class="form-select" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->code }}" @selected(old('role', $assignedRole) === $role->code)>{{ __('admin.staff.roles.'.$role->code) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="role-permissions">
                <h2>{{ __('admin.staff.permission_summary') }}</h2>
                <p>{{ __('admin.staff.permission_summary_description') }}</p>
                @foreach (['super_admin', 'manager', 'operator'] as $roleCode)
                    <details>
                        <summary>{{ __('admin.staff.roles.'.$roleCode) }}</summary>
                        <ul>
                            @foreach (__('admin.staff.permission_sets.'.$roleCode) as $permission)
                                <li>{{ $permission }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
            <label class="checkbox-field form-check">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffMember->exists ? $staffMember->is_active : true) == true)>
                {{ __('admin.staff.active') }}
            </label>
            <div class="form-actions">
                <a class="text-button" href="{{ route('admin.staff.index') }}">{{ __('admin.cancel') }}</a>
                <button class="btn btn-primary primary-button" type="submit">{{ __('admin.staff.save') }}</button>
            </div>
        </form>
    </section>
@endsection
