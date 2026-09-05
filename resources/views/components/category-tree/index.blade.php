@props([
    'categoriesByParent',
    'routePrefix',
])

@php($totalCategories = $categoriesByParent->flatten(1)->count())

<div class="w2a-tree-wrapper">
    <div class="w2a-tree-toolbar">
        <div class="w2a-tree-search-wrap">
            <i class="fa fa-search w2a-tree-search-icon" aria-hidden="true"></i>
            <label class="sr-only" for="w2a_tree_search_input">ابحث في التصنيفات</label>
            <input type="search" id="w2a_tree_search_input" class="w2a-tree-search-input" placeholder="ابحث في التصنيفات..." autocomplete="off">
            <button type="button" id="w2a_tree_search_clear" class="w2a-tree-search-clear" hidden aria-label="مسح البحث"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="w2a-tree-actions">
            <button type="button" class="w2a-tree-btn" id="w2a_tree_expand_all"><i class="fa fa-expand" aria-hidden="true"></i> توسيع الكل</button>
            <button type="button" class="w2a-tree-btn" id="w2a_tree_collapse_all"><i class="fa fa-compress" aria-hidden="true"></i> طي الكل</button>
            <span class="w2a-tree-badge"><i class="fa fa-folder" aria-hidden="true"></i> {{ $totalCategories }} تصنيف</span>
        </div>
    </div>

    @if($totalCategories > 0)
        <nav class="w2a-tree-container" aria-label="شجرة التصنيفات">
            <ul class="w2a-tree-root">
                @foreach($categoriesByParent->get(0, collect()) as $category)
                    <x-category-tree.node
                        :category="$category"
                        :categories-by-parent="$categoriesByParent"
                        :route-prefix="$routePrefix"
                        :level="1"
                    />
                @endforeach
            </ul>
        </nav>
    @else
        <div class="w2a-tree-empty" role="status"><i class="fa fa-info-circle" aria-hidden="true"></i> لا توجد تصنيفات متاحة حاليًا.</div>
    @endif
</div>
