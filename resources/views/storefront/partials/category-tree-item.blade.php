<li>
    <a class="@if ($currentSlug === $treeItem['slug']) is-current @endif" href="{{ route('store.category', $treeItem['slug']) }}">{{ $treeItem['name'] }}</a>
    @if ($treeItem['children']->isNotEmpty())
        <ul>
            @foreach ($treeItem['children'] as $treeItem)
                @include('storefront.partials.category-tree-item', ['treeItem' => $treeItem, 'currentSlug' => $currentSlug])
            @endforeach
        </ul>
    @endif
</li>
