@extends('admin.layouts.app')

@section('title', __('admin.homepage.title'))

@section('content')
    <section class="page-heading admin-page-heading">
        <div><p class="eyebrow">{{ __('admin.homepage.eyebrow') }}</p><h1>{{ __('admin.homepage.title') }}</h1><p class="text-body-secondary mb-0">{{ __('admin.homepage.description') }}</p></div>
        <div class="admin-heading-actions"><a class="btn btn-outline-secondary" href="{{ route('admin.homepage.collections') }}">{{ __('admin.homepage.manage_collections') }}</a><a class="btn btn-primary primary-button" href="{{ route('admin.homepage.banners.create') }}">{{ __('admin.homepage.add_banner') }}</a></div>
    </section>

    @if ($sections->isEmpty())<form method="POST" action="{{ route('admin.homepage.initialize') }}" class="alert alert-info d-flex align-items-center justify-content-between gap-3 flex-wrap">@csrf<span>{{ __('admin.homepage.defaults_help') }}</span><button class="btn btn-primary primary-button" type="submit">{{ __('admin.homepage.initialize') }}</button></form>@endif

    <section class="card panel customer-list-panel mb-4">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.homepage.sections') }}</p><h2>{{ __('admin.homepage.sections') }}</h2></div></div>
        <form method="POST" action="{{ route('admin.homepage.sections.update') }}">@csrf @method('PATCH')
            <div class="table-scroll"><table class="table table-hover align-middle orders-table mb-0"><thead><tr><th>{{ __('admin.homepage.section') }}</th><th>{{ __('admin.homepage.section_type') }}</th><th>{{ __('admin.homepage.sort_order') }}</th><th>{{ __('admin.homepage.visible') }}</th></tr></thead><tbody>
                @forelse ($sections as $index => $section)
                    @php($translation = $section->translations->firstWhere('locale', app()->getLocale()) ?? $section->translations->first())
                    <tr><td><strong>{{ $translation?->heading ?: $section->code }}</strong></td><td>{{ __('admin.homepage.section_types.'.$section->section_type) }}</td><td><input class="form-control" type="number" min="0" max="1000000" name="sections[{{ $index }}][sort_order]" value="{{ old('sections.'.$index.'.sort_order', $section->sort_order) }}" required><input type="hidden" name="sections[{{ $index }}][id]" value="{{ $section->id }}"></td><td><input type="hidden" name="sections[{{ $index }}][is_enabled]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="sections[{{ $index }}][is_enabled]" value="1" @checked(old('sections.'.$index.'.is_enabled', $section->is_enabled))><span class="form-check-label">{{ __('admin.homepage.enabled') }}</span></label></td></tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4">{{ __('admin.homepage.no_sections') }}</td></tr>
                @endforelse
            </tbody></table></div>
            @if ($sections->isNotEmpty())<div class="p-3"><button class="btn btn-primary primary-button" type="submit">{{ __('admin.homepage.save_sections') }}</button></div>@endif
        </form>
    </section>

    <section class="card panel customer-list-panel">
        <div class="panel-heading"><div><p class="eyebrow">{{ __('admin.homepage.banners') }}</p><h2>{{ __('admin.homepage.banners') }}</h2></div><span class="panel-count">{{ $banners->total() }}</span></div>
        <div class="table-scroll"><table class="table table-hover align-middle orders-table mb-0"><thead><tr><th>{{ __('admin.homepage.preview') }}</th><th>{{ __('admin.homepage.banner') }}</th><th>{{ __('admin.homepage.placement') }}</th><th>{{ __('admin.homepage.sort_order') }}</th><th>{{ __('admin.homepage.visible') }}</th><th></th></tr></thead><tbody>
            @forelse ($banners as $banner)
                @php($translation = $banner->translations->firstWhere('locale', app()->getLocale()) ?? $banner->translations->first())
                <tr><td><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}" alt="{{ $translation?->alt_text }}" class="rounded" style="width:112px;height:64px;object-fit:cover"></td><td>{{ $translation?->title ?: $translation?->alt_text }}</td><td>{{ __('admin.homepage.placements.'.$banner->placement) }}</td><td>{{ $banner->sort_order }}</td><td><span class="badge {{ $banner->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $banner->is_active ? __('admin.homepage.enabled') : __('admin.homepage.hidden') }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.homepage.banners.edit', $banner) }}">{{ __('admin.homepage.edit') }}</a></td></tr>
            @empty<tr><td colspan="6" class="text-center py-4">{{ __('admin.homepage.no_banners') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">{{ $banners->links() }}</div>
    </section>
@endsection
