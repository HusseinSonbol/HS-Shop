@extends('admin.layouts.app')

@section('title', __('admin.homepage.manage_collections'))

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ __('admin.homepage.title') }}</p><h1>{{ __('admin.homepage.manage_collections') }}</h1><p class="text-body-secondary mb-0">{{ __('admin.homepage.collections_note') }}</p></div><a class="btn btn-outline-secondary" href="{{ route('admin.homepage.index') }}">{{ __('admin.homepage.back') }}</a></section>
    <form method="GET" action="{{ route('admin.homepage.collections') }}" class="card panel customer-list-panel p-3 mb-4"><div class="row g-2"><div class="col"><label class="visually-hidden" for="product_q">{{ __('admin.homepage.find_product') }}</label><input id="product_q" name="product_q" class="form-control" value="{{ $filters['product_q'] ?? '' }}" placeholder="{{ __('admin.homepage.find_product') }}"></div><div class="col-auto"><button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.homepage.search') }}</button></div></div></form>
    @forelse ($collections as $collection)
        @php($translation = $collection->translations->firstWhere('locale', app()->getLocale()) ?? $collection->translations->first())
        <section class="card panel customer-list-panel mb-4">
            <div class="panel-heading"><div><p class="eyebrow">{{ $collection->code }}</p><h2>{{ $translation?->title ?: __('admin.homepage.collections.'.$collection->code) }}</h2></div><span class="panel-count">{{ $collection->products->count() }}</span></div>
            <form method="POST" action="{{ route('admin.homepage.collections.items.store', $collection) }}" class="p-3 border-bottom"><div class="row g-2 align-items-end"><div class="col-md-9"><label class="form-label" for="product-{{ $collection->id }}">{{ __('admin.homepage.add_product') }}</label><select id="product-{{ $collection->id }}" name="product_id" class="form-select" required><option value="">{{ __('admin.homepage.choose_product') }}</option>@foreach($products as $product)@php($productName = $product->translations->firstWhere('locale', app()->getLocale())?->name ?? $product->translations->firstWhere('locale', 'en')?->name)<option value="{{ $product->id }}">{{ $product->sku }} · {{ $productName }}</option>@endforeach</select></div><div class="col-md-3"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn btn-primary primary-button w-100" type="submit">{{ __('admin.homepage.add_product') }}</button></div></div></form>
            @if ($collection->products->isNotEmpty())
                <form method="POST" action="{{ route('admin.homepage.collections.items.order', $collection) }}">@csrf @method('PATCH')
                    <div class="table-scroll"><table class="table table-hover align-middle orders-table mb-0"><thead><tr><th>{{ __('admin.homepage.position') }}</th><th>{{ __('admin.homepage.product') }}</th><th>{{ __('admin.homepage.availability') }}</th><th></th></tr></thead><tbody>
                        @foreach ($collection->products as $index => $product)
                            @php($productName = $product->translations->firstWhere('locale', app()->getLocale())?->name ?? $product->translations->firstWhere('locale', 'en')?->name)
                            <tr><td><input type="hidden" name="items[{{ $index }}][id]" value="{{ $product->pivot->id }}"><input type="number" name="items[{{ $index }}][position]" class="form-control" min="1" max="1000000" value="{{ old('items.'.$index.'.position', $product->pivot->position) }}" required></td><td>{{ $productName }} <span class="text-body-secondary">{{ $product->sku }}</span></td><td>{{ __('admin.products.availability_values.'.$product->store_availability_status) }}</td><td class="text-end"><button class="btn btn-sm btn-outline-danger" type="submit" form="remove-item-{{ $product->pivot->id }}">{{ __('admin.homepage.remove') }}</button></td></tr>
                        @endforeach
                    </tbody></table></div><div class="p-3"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.homepage.save_order') }}</button></div>
                </form>
                @foreach ($collection->products as $product)
                    <form id="remove-item-{{ $product->pivot->id }}" method="POST" action="{{ route('admin.homepage.collections.items.destroy', [$collection, $product->pivot->id]) }}" class="d-none">@csrf @method('DELETE')</form>
                @endforeach
            @else
                <div class="p-4 text-center text-body-secondary">{{ __('admin.homepage.collection_empty') }}</div>
            @endif
        </section>
    @empty
        <div class="alert alert-warning">{{ __('admin.homepage.collections_not_seeded') }}</div>
    @endforelse
    <div class="mt-3">{{ $products->links() }}</div>
@endsection
