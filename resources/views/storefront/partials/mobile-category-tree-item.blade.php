<li class="hs-mobile-category-item hs-mobile-category-item--level-{{ $level }}">
    <div class="hs-mobile-category-row">
        <a href="{{ route('store.category', $treeItem['slug']) }}">{{ $treeItem['name'] }}</a>
        @if ($treeItem['children']->isNotEmpty())
            @php($childrenId = 'hs-mobile-category-'.$treeItem['id'])
            <button
                class="hs-mobile-category-toggle"
                type="button"
                data-mobile-category-toggle
                data-expand-label="{{ __('store.navigation.expand_category', ['category' => $treeItem['name']]) }}"
                data-collapse-label="{{ __('store.navigation.collapse_category', ['category' => $treeItem['name']]) }}"
                aria-expanded="false"
                aria-controls="{{ $childrenId }}"
                aria-label="{{ __('store.navigation.expand_category', ['category' => $treeItem['name']]) }}"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"></path></svg>
            </button>
        @endif
    </div>
    @if ($treeItem['children']->isNotEmpty())
        <ul class="hs-mobile-category-children" id="{{ $childrenId }}" hidden>
            @foreach ($treeItem['children'] as $treeItem)
                @include('storefront.partials.mobile-category-tree-item', ['treeItem' => $treeItem, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
