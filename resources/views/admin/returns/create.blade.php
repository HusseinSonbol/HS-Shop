@extends('admin.layouts.app')

@section('title', __('admin.returns.create'))

@section('content')
    <section class="page-heading staff-page-heading"><div><p class="eyebrow" dir="ltr">{{ $record->order_number }}</p><h1>{{ __('admin.returns.create') }}</h1><p class="page-description">{{ $record->customer_name }} · <span dir="ltr">{{ $record->phone }}</span></p></div><a class="btn btn-outline-secondary secondary-button" href="{{ route('admin.orders.show', $record->id) }}">{{ __('admin.orders.back') }}</a></section>
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="alert alert-warning">{{ __('admin.returns.validation.order_not_delivered') }}</div>
    <section class="card panel customer-section-panel">
        <form method="POST" action="{{ route('admin.returns.store', $record->id) }}">@csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="return-reason">{{ __('admin.returns.reason') }}</label><select class="form-select" id="return-reason" name="reason_code" required><option value="damaged">{{ __('admin.returns.damaged') }}</option><option value="manufacturing_defect">{{ __('admin.returns.manufacturing_defect') }}</option></select></div>
                <div class="col-12"><label class="form-label" for="return-description">{{ __('admin.returns.reason_details') }}</label><textarea class="form-control" id="return-description" name="reason" minlength="5" maxlength="3000" rows="3" required>{{ old('reason') }}</textarea></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" id="unused-claim" type="checkbox" name="unused_claimed" value="1" required><label class="form-check-label" for="unused-claim">{{ __('admin.returns.unused_claimed') }}</label></div></div>
            </div>
            <h2 class="h5 mt-4">{{ __('admin.returns.items') }}</h2>
            <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>{{ __('admin.returns.product') }}</th><th>{{ __('admin.returns.sku') }}</th><th>{{ __('admin.returns.variant') }}</th><th>{{ __('admin.returns.ordered_quantity') }}</th><th>{{ __('admin.returns.previously_requested') }}</th><th>{{ __('admin.returns.eligible_quantity') }}</th><th>{{ __('admin.returns.return_quantity') }}</th></tr></thead><tbody>
            @foreach ($items as $item)
                @php($variant = $item->variant_snapshot ? json_decode($item->variant_snapshot, true) : [])
                <tr><td>{{ $item->product_name_snapshot }}</td><td dir="ltr">{{ $item->sku_snapshot }}</td><td>{{ $variant['variant_name'] ?? collect($variant['attributes'] ?? [])->map(fn ($attribute) => ($attribute['name'] ?? '').': '.($attribute['value'] ?? ''))->implode(' · ') }}</td><td>{{ $item->quantity }}</td><td>{{ $item->already_requested }}</td><td>{{ $item->eligible_quantity }}</td><td><input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->order_item_id }}"><input class="form-control" type="number" step="0.001" min="0" max="{{ $item->eligible_quantity }}" name="items[{{ $loop->index }}][quantity]" value="0" @disabled($item->eligible_quantity <= 0)></td></tr>
            @endforeach
            </tbody></table></div>
            <div class="mt-3"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.returns.create') }}</button></div>
        </form>
    </section>
@endsection
