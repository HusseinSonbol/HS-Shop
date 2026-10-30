@extends('admin.layouts.app')

@section('title', $supplier->name)

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ $supplier->code }} · {{ __('admin.suppliers.types.'.$supplier->supplier_type) }}</p><h1>{{ $supplier->name }}</h1></div><div class="admin-heading-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.suppliers.edit', $supplier) }}">{{ __('admin.suppliers.edit') }}</a><a class="btn btn-primary primary-button" href="{{ route('admin.suppliers.offers.create', $supplier) }}">{{ __('admin.suppliers.add_offer') }}</a></div></section>
    <div class="card card-body mb-4"><div class="row g-3"><div class="col-md-4"><strong>{{ __('admin.suppliers.owner_name') }}:</strong> {{ $supplier->owner_name ?: '—' }}</div><div class="col-md-4"><strong>{{ __('admin.suppliers.owner_mobile') }}:</strong> <span dir="ltr">{{ $supplier->owner_phone ?: '—' }}</span></div><div class="col-md-4"><strong>{{ __('admin.suppliers.contact_name') }}:</strong> {{ $supplier->contact_name ?: '—' }}</div><div class="col-md-4"><strong>{{ __('admin.suppliers.phone') }}:</strong> <span dir="ltr">{{ $supplier->phone ?: '—' }}</span></div><div class="col-md-4"><strong>{{ __('admin.suppliers.email') }}:</strong> {{ $supplier->email ?: '—' }}</div>
        @php($addressLabels = ['governorate', 'city', 'district', 'street', 'building', 'floor', 'landmark', 'postal_code'])
        @if ($supplier->address_json)
            <div class="col-12"><strong>{{ __('admin.suppliers.address') }}:</strong><div class="row g-2 mt-1">@foreach ($addressLabels as $addressField) @if (filled($supplier->address_json[$addressField] ?? null))<div class="col-md-4"><span class="text-body-secondary">{{ __('admin.suppliers.address_fields.'.$addressField) }}:</span> {{ $supplier->address_json[$addressField] }}</div>@endif @endforeach</div></div>
        @endif
        <div class="col-12"><strong>{{ __('admin.suppliers.notes') }}:</strong> {{ $supplier->notes ?: '—' }}</div></div></div>
    <h2 class="h5 mb-3">{{ __('admin.suppliers.offers') }}</h2>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ __('admin.suppliers.product') }}</th><th>{{ __('admin.suppliers.cost') }}</th><th>{{ __('admin.suppliers.availability') }}</th><th>{{ __('admin.suppliers.validity') }}</th><th>{{ __('admin.suppliers.status') }}</th><th>{{ __('admin.suppliers.actions') }}</th></tr></thead><tbody>
        @forelse ($supplier->productSuppliers as $offer)
            @php($productName = $offer->product?->translations->firstWhere('locale', app()->getLocale())?->name ?? $offer->product?->translations->firstWhere('locale', 'ar')?->name ?? __('admin.suppliers.deleted_product'))
            <tr><td>{{ $productName }}</td><td>{{ $offer->unit_cost }} {{ $offer->currency }}</td><td>{{ __('admin.suppliers.availability_values.'.$offer->supplier_availability_status) }}</td><td>{{ $offer->valid_from?->format('Y-m-d') ?: '—' }} – {{ $offer->valid_until?->format('Y-m-d') ?: '—' }}</td><td>{{ $offer->is_active ? __('admin.suppliers.active') : __('admin.suppliers.inactive') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.suppliers.offers.edit', [$supplier, $offer]) }}">{{ __('admin.suppliers.view_history_edit') }}</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-body-secondary py-5">{{ __('admin.suppliers.no_offers') }}</td></tr>
        @endforelse
    </tbody></table></div></div>
@endsection
