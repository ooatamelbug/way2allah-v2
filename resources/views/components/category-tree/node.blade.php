@props([
    'category',
    'categoriesByParent',
    'routePrefix',
    'level' => 1,
])

@php
    $children = $categoriesByParent->get($category->id, collect());
    $hasChildren = $children->isNotEmpty();
    $childrenId = 'w2a-tree-children-'.$category->id;
@endphp

<li class="w2a-tree-node level-{{ $level }} {{ $hasChildren ? 'has-children' : '' }}" data-title="{{ $category->title }}">
    <div class="w2a-tree-item">
        @if($hasChildren)
            <button type="button" class="w2a-tree-toggle" aria-expanded="false" aria-controls="{{ $childrenId }}" aria-label="توسيع أو طي {{ $category->title }}">
                <i class="fa fa-chevron-left w2a-tree-arrow" aria-hidden="true"></i>
            </button>
            <i class="fa fa-folder w2a-tree-icon folder-icon" aria-hidden="true"></i>
        @else
            <span class="w2a-tree-spacer" aria-hidden="true"></span>
            <i class="fa fa-file-text-o w2a-tree-icon file-icon" aria-hidden="true"></i>
        @endif

        <a href="{{ $routePrefix }}{{ $category->id }}.htm" class="w2a-tree-link">{{ $category->title }}</a>

        @if($hasChildren)
            <span class="w2a-tree-sub-count" aria-label="{{ $children->count() }} تصنيفات فرعية">{{ $children->count() }}</span>
        @endif
    </div>

    @if($hasChildren)
        <ul class="w2a-tree-sub-list" id="{{ $childrenId }}" hidden>
            @foreach($children as $child)
                <x-category-tree.node
                    :category="$child"
                    :categories-by-parent="$categoriesByParent"
                    :route-prefix="$routePrefix"
                    :level="$level + 1"
                />
            @endforeach
        </ul>
    @endif
</li>
