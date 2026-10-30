@extends('admin.layouts.app')

@section('title', __('admin.products.title'))

@section('content')
    <section class="page-heading staff-page-heading">
        <div><p class="eyebrow">{{ __('admin.admin_panel') }}</p><h1>{{ __('admin.products.title') }}</h1><p class="page-description">{{ __('admin.products.page_description') }}</p></div>
        <a class="btn btn-primary primary-button" href="{{ route('admin.products.create') }}">{{ __('admin.products.create') }}</a>
    </section>

    <section class="card panel customer-list-panel">
        <form method="GET" action="{{ route('admin.products.index') }}" class="customer-filters">
            <label for="product-search">{{ __('admin.products.search') }}</label>
            <input class="form-control" id="product-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('admin.products.search_hint') }}">
            <select class="form-select" name="status" aria-label="{{ __('admin.products.status') }}"><option value="">{{ __('admin.products.any_status') }}</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('admin.products.draft') }}</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('admin.products.published') }}</option></select>
            <select class="form-select" name="scope" aria-label="{{ __('admin.products.scope') }}"><option value="active" @selected($filters['scope'] === 'active')>{{ __('admin.products.active_scope') }}</option><option value="archived" @selected($filters['scope'] === 'archived')>{{ __('admin.products.archived_scope') }}</option></select>
            <button class="btn btn-primary primary-button customer-search-button" type="submit">{{ __('admin.products.search') }}</button>
        </form>

        @if ($products->isEmpty())
            <div class="empty-state"><p>{{ __('admin.products.empty') }}</p></div>
        @else
            <div class="table-scroll"><table class="table table-hover align-middle orders-table">
                <thead><tr><th>{{ __('admin.products.image') }}</th><th>{{ __('admin.products.group_sku') }}</th><th>{{ __('admin.products.name') }}</th><th>{{ __('admin.products.type') }}</th><th>{{ __('admin.products.variants_count') }}</th><th>{{ __('admin.products.status') }}</th><th>{{ __('admin.products.availability') }}</th><th>{{ __('admin.products.actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>@if ($product->images->first())<img class="admin-product-thumb" src="{{ Storage::disk('public')->url($product->images->first()->path) }}" alt="">@else<span class="text-body-secondary">—</span>@endif</td>
                            <td class="order-number" dir="ltr">{{ $product->sku }}</td>
                            <td>{{ $product->translations->firstWhere('locale', app()->getLocale())?->name ?? $product->translations->firstWhere('locale', 'ar')?->name }}</td>
                            <td>{{ __('admin.products.types.'.$product->product_type) }}</td>
                            <td>{{ number_format($product->variants_count) }}</td>
                            <td>{{ __('admin.products.'.$product->status) }}</td>
                            <td>{{ __('admin.products.availability_values.'.$product->store_availability_status) }}</td>
                            <td><div class="staff-actions">
                                @if ($product->trashed())
                                    <form method="POST" action="{{ route('admin.products.restore', $product) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-link text-button p-0">{{ __('admin.products.restore') }}</button></form>
                                @else
                                    <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.products.show', $product) }}">{{ __('admin.products.view') }}</a>
                                    <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.products.edit', $product) }}">{{ __('admin.products.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.products.archive', $product) }}" onsubmit="return confirm(@js(__('admin.products.confirm_archive'))) ">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-button text-danger p-0">{{ __('admin.products.archive') }}</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="pagination-wrap">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
