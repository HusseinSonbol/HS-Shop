<li class="hs-desktop-category-item">
    <a class="hs-desktop-category-link" href="{{ route('store.category', $treeItem['slug']) }}">{{ $treeItem['name'] }}</a>
    @if ($treeItem['children']->isNotEmpty())
        <ul class="hs-desktop-category-children">
            @foreach ($treeItem['children'] as $treeItem)
                @include('storefront.partials.desktop-category-tree-item', ['treeItem' => $treeItem])
            @endforeach
        </ul>
    @endif
</li>
