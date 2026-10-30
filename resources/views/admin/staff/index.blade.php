@extends('admin.layouts.app')

@section('title', __('admin.staff.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div>
            <p class="eyebrow">{{ __('admin.admin_panel') }}</p>
            <h1>{{ __('admin.staff.title') }}</h1>
            <p class="page-description">{{ __('admin.staff.description') }}</p>
        </div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.staff.create') }}">{{ __('admin.staff.create') }}</a>
    </section>

    <section class="card panel">
        @if ($staff->isEmpty())
            <div class="empty-state"><p>{{ __('admin.staff.empty') }}</p></div>
        @else
            <div class="table-scroll">
                <table class="table table-hover align-middle orders-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.staff.name') }}</th>
                            <th>{{ __('admin.staff.email') }}</th>
                            <th>{{ __('admin.staff.role') }}</th>
                            <th>{{ __('admin.staff.status') }}</th>
                            <th>{{ __('admin.staff.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td>{{ $member->name }}</td>
                                <td dir="ltr">{{ $member->email }}</td>
                                <td>{{ __('admin.staff.roles.'.$member->roles->first()?->code) }}</td>
                                <td>{{ __($member->is_active ? 'admin.staff.active' : 'admin.staff.inactive') }}</td>
                                <td>
                                    <div class="staff-actions">
                                        <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.staff.edit', $member) }}">{{ __('admin.staff.edit') }}</a>
                                        <form method="POST" action="{{ route('admin.staff.active', $member) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-link text-button p-0" type="submit">{{ __($member->is_active ? 'admin.staff.deactivate' : 'admin.staff.activate') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.staff.destroy', $member) }}" onsubmit="return confirm(@js(__('admin.staff.confirm_archive')))">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-link text-button text-danger p-0" type="submit">{{ __('admin.staff.archive') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $staff->links() }}</div>
        @endif
    </section>
@endsection
