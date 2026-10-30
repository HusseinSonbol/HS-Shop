@extends('admin.layouts.app')

@section('title', $offer->exists ? __('admin.suppliers.offer_history_title') : __('admin.suppliers.add_offer'))

@section('content')
    <div class="mb-4"><a href="{{ route('admin.suppliers.show', $supplier) }}" class="link-secondary">{{ $supplier->name }}</a><h1 class="h3 mt-2">{{ $offer->exists ? __('admin.suppliers.offer_history_title') : __('admin.suppliers.add_offer') }}</h1></div>
    <form method="POST" action="{{ $offer->exists ? route('admin.suppliers.offers.update', [$supplier, $offer]) : route('admin.suppliers.offers.store', $supplier) }}" class="card card-body gap-4 mb-4">
        @csrf @if ($offer->exists) @method('PUT') @endif
        @if ($offer->exists)
            <input type="hidden" name="product_id" value="{{ old('product_id', $offer->product_id) }}">
            <div><span class="form-label d-block">{{ __('admin.suppliers.product') }}</span><strong>{{ $offer->product?->translations->firstWhere('locale', app()->getLocale())?->name ?? $offer->product?->translations->firstWhere('locale', 'ar')?->name ?? __('admin.suppliers.deleted_product') }}</strong></div>
        @else
            <div><label class="form-label" for="product_id">{{ __('admin.suppliers.product') }}</label><select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required><option value="">{{ __('admin.suppliers.choose_product') }}</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->translations->firstWhere('locale', app()->getLocale())?->name ?? $product->translations->firstWhere('locale', 'ar')?->name ?? $product->sku }}</option>@endforeach</select>@error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @endif
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="unit_cost">{{ __('admin.suppliers.cost') }} (EGP)</label><input id="unit_cost" type="number" step="0.0001" min="0" name="unit_cost" class="form-control @error('unit_cost') is-invalid @enderror" value="{{ old('unit_cost', $offer->unit_cost) }}" required dir="ltr">@error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="supplier_availability_status">{{ __('admin.suppliers.availability') }}</label><select id="supplier_availability_status" name="supplier_availability_status" class="form-select @error('supplier_availability_status') is-invalid @enderror" required>@foreach (['unknown', 'available', 'unavailable'] as $status)<option value="{{ $status }}" @selected(old('supplier_availability_status', $offer->supplier_availability_status ?? 'unknown') === $status)>{{ __('admin.suppliers.availability_values.'.$status) }}</option>@endforeach</select>@error('supplier_availability_status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="is_active">{{ __('admin.suppliers.offer_status') }}</label><input type="hidden" name="is_active" value="0"><select id="is_active" name="is_active" class="form-select"><option value="1" @selected((string) old('is_active', $offer->exists ? (int) $offer->is_active : 1) === '1')>{{ __('admin.suppliers.active') }}</option><option value="0" @selected((string) old('is_active', $offer->exists ? (int) $offer->is_active : 1) === '0')>{{ __('admin.suppliers.inactive') }}</option></select></div>
            <div class="col-md-6"><label class="form-label" for="valid_from">{{ __('admin.suppliers.valid_from') }}</label><input id="valid_from" type="datetime-local" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ old('valid_from', $offer->valid_from?->format('Y-m-d\TH:i')) }}">@error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="valid_until">{{ __('admin.suppliers.valid_until') }}</label><input id="valid_until" type="datetime-local" name="valid_until" class="form-control @error('valid_until') is-invalid @enderror" value="{{ old('valid_until', $offer->valid_until?->format('Y-m-d\TH:i')) }}">@error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="change_reason">{{ __('admin.suppliers.change_reason') }}</label><input id="change_reason" name="change_reason" class="form-control" value="{{ old('change_reason') }}" maxlength="255"></div>
        </div>
        <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.suppliers.show', $supplier) }}">{{ __('admin.suppliers.cancel') }}</a><button class="btn btn-primary primary-button" type="submit">{{ __('admin.suppliers.save_offer') }}</button></div>
    </form>

    @if ($offer->exists)
        <h2 class="h5 mb-3">{{ __('admin.suppliers.offer_history_title') }}</h2>
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ __('admin.suppliers.version') }}</th><th>{{ __('admin.suppliers.cost') }}</th><th>{{ __('admin.suppliers.availability') }}</th><th>{{ __('admin.suppliers.validity') }}</th><th>{{ __('admin.suppliers.changed_by') }}</th><th>{{ __('admin.suppliers.changed_at') }}</th><th>{{ __('admin.suppliers.change_reason') }}</th></tr></thead><tbody>
            @forelse ($offer->history as $entry)
                <tr><td>{{ $entry->version_no }}</td><td>{{ $entry->unit_cost }} {{ $entry->currency }}</td><td>{{ __('admin.suppliers.availability_values.'.$entry->supplier_availability_status) }}</td><td>{{ $entry->valid_from ? \Illuminate\Support\Carbon::parse($entry->valid_from)->format('Y-m-d') : '—' }} – {{ $entry->valid_until ? \Illuminate\Support\Carbon::parse($entry->valid_until)->format('Y-m-d') : '—' }}</td><td>{{ $entry->changedBy?->name ?? '—' }}</td><td>{{ $entry->changed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td><td>{{ $entry->change_reason ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('admin.suppliers.no_history') }}</td></tr>
            @endforelse
        </tbody></table></div></div>
    @endif
@endsection
