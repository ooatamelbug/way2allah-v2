@props(['items', 'category'])

<div class="w2a-cat-series-grid">
    @foreach ($items as $item)
        @php($authorName = trim(($item->prename ?? '').' '.($item->name ?? '')))
        <a href="/category-series-{{ $item->id }}-{{ $category }}.htm" class="w2a-cat-series-card">
            <div class="w2a-cat-series-header">
                <span class="w2a-cat-series-icon-wrap"><i class="fa fa-folder-open-o" aria-hidden="true"></i></span>
                <span class="w2a-cat-series-count"><i class="fa fa-play-circle" aria-hidden="true"></i> {{ number_format((int) $item->count) }} مادة</span>
            </div>
            <div class="w2a-cat-series-body">
                <h3 class="w2a-cat-series-title">{{ trim((string) $item->title) }}</h3>
                @if ($authorName !== '')
                    <span class="w2a-cat-series-author"><i class="fa fa-user-circle-o" aria-hidden="true"></i> {{ $authorName }}</span>
                @endif
                <span class="w2a-cat-series-cta">
                    <span>تصفح السلسلة</span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </span>
            </div>
        </a>
    @endforeach
</div>
