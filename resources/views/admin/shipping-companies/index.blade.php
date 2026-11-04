@extends('admin.layouts.app')

@section('title', __('admin.shipping_companies.title'))

@section('content')
<div class="page-heading mb-4"><h1>{{ __('admin.shipping_companies.title') }}</h1><p class="text-secondary mb-0">{{ __('admin.shipping_companies.help') }}</p></div>
@if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
<section class="card panel customer-section-panel mb-4">
    <div class="panel-heading"><div><h2>{{ __('admin.shipping_companies.add') }}</h2></div></div>
    <form method="POST" action="{{ route('admin.shipping-companies.store') }}">@csrf
        <div class="row g-3 align-items-end"><div class="col-md-8"><label for="company-name" class="form-label">{{ __('admin.shipping_companies.name') }}</label><input id="company-name" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="160" value="{{ old('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-4"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.shipping_companies.add') }}</button></div></div>
    </form>
</section>
<section class="card panel customer-section-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ __('admin.shipping_companies.name') }}</th><th>{{ __('admin.shipping_companies.status') }}</th><th>{{ __('admin.shipping_companies.actions') }}</th></tr></thead><tbody>
@forelse ($companies as $company)
<tr><td><form class="d-flex gap-2" method="POST" action="{{ route('admin.shipping-companies.update', $company->id) }}">@csrf @method('PUT')<label class="visually-hidden" for="company-{{ $company->id }}">{{ __('admin.shipping_companies.name') }}</label><input id="company-{{ $company->id }}" class="form-control" name="name" value="{{ $company->name }}" maxlength="160" required><button class="btn btn-primary primary-button" type="submit">{{ __('admin.shipping_companies.save') }}</button></form></td><td><span class="badge {{ $company->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('admin.shipping_companies.statuses.'.($company->is_active ? 'active' : 'inactive')) }}</span></td><td><form method="POST" action="{{ route('admin.shipping-companies.active', $company->id) }}">@csrf @method('PATCH')<button class="btn {{ $company->is_active ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ __('admin.shipping_companies.'.($company->is_active ? 'deactivate' : 'activate')) }}</button></form></td></tr>
@empty<tr><td colspan="3" class="text-center text-secondary">{{ __('admin.shipping_companies.empty') }}</td></tr>@endforelse
</tbody></table></div><div class="px-3">{{ $companies->links() }}</div></section>
@endsection
