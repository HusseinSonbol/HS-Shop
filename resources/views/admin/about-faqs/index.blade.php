@extends('admin.layouts.app')

@section('title', __('admin.about_faqs.title'))

@section('content')
    <section class="page-heading admin-page-heading"><div><p class="eyebrow">{{ __('admin.about_faqs.eyebrow') }}</p><h1>{{ __('admin.about_faqs.title') }}</h1><p class="text-body-secondary mb-0">{{ __('admin.about_faqs.description') }}</p></div><a class="btn btn-primary primary-button" href="{{ route('admin.about-faqs.create') }}">{{ __('admin.about_faqs.add') }}</a></section>
    <div class="d-flex gap-2 mb-3"><a class="btn {{ $filters['scope'] === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.about-faqs.index') }}">{{ __('admin.about_faqs.active') }}</a><a class="btn {{ $filters['scope'] === 'archived' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.about-faqs.index', ['scope' => 'archived']) }}">{{ __('admin.about_faqs.archived_scope') }}</a></div>
    <section class="card panel customer-list-panel"><div class="table-scroll"><table class="table table-hover align-middle orders-table mb-0"><thead><tr><th>{{ __('admin.about_faqs.question') }}</th><th>{{ __('admin.about_faqs.sort_order') }}</th><th>{{ __('admin.about_faqs.status') }}</th><th></th></tr></thead><tbody>
        @forelse($faqs as $faq)
            @php($translation = $faq->translations->firstWhere('locale', app()->getLocale()) ?? $faq->translations->first())
            <tr><td>{{ $translation?->question }}</td><td>{{ $faq->sort_order }}</td><td>{{ $faq->trashed() ? __('admin.about_faqs.archived_label') : ($faq->is_active ? __('admin.about_faqs.active_label') : __('admin.about_faqs.hidden')) }}</td><td class="text-end">@if($faq->trashed())<form method="POST" action="{{ route('admin.about-faqs.restore', $faq) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success" type="submit">{{ __('admin.about_faqs.restore') }}</button></form>@else<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.about-faqs.edit', $faq) }}">{{ __('admin.about_faqs.edit') }}</a><form method="POST" action="{{ route('admin.about-faqs.archive', $faq) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">{{ __('admin.about_faqs.archive') }}</button></form>@endif</td></tr>
        @empty<tr><td colspan="4" class="text-center py-4">{{ __('admin.about_faqs.empty') }}</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $faqs->links() }}</div></section>
@endsection
