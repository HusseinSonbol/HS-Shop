<tr>
    <td><span style="padding-inline-start: {{ $depth * 1.5 }}rem">{{ $category->translations->firstWhere('locale', app()->getLocale())?->name ?? $category->translations->firstWhere('locale', 'ar')?->name }}</span></td>
    <td><code>{{ $category->slug }}</code></td>
    <td>{{ $category->sort_order }}</td>
    <td>@if ($category->trashed()) {{ __('admin.categories.archived_badge') }} @elseif ($category->is_active) {{ __('admin.categories.active_badge') }} @else {{ __('admin.categories.inactive_badge') }} @endif</td>
    <td>
        <div class="d-flex gap-2">
            @if ($category->trashed())
                <form method="POST" action="{{ route('admin.categories.restore', $category) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-link text-button p-0" type="submit">{{ __('admin.categories.restore') }}</button></form>
            @else
                <a class="btn btn-sm btn-link text-button p-0" href="{{ route('admin.categories.edit', $category) }}">{{ __('admin.categories.edit') }}</a>
                <form method="POST" action="{{ route('admin.categories.archive', $category) }}" onsubmit="return confirm(@js(__('admin.categories.confirm_archive'))) ">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-button text-danger p-0" type="submit">{{ __('admin.categories.archive') }}</button></form>
            @endif
        </div>
    </td>
</tr>
@foreach ($category->tree_children as $child)
    @include('admin.categories.partials.row', ['category' => $child, 'depth' => $depth + 1])
@endforeach
