@props(['items'])

<div class="w2a-anasheed-wrap">
    <div class="w2a-reciters-toolbar">
        <div class="w2a-reciters-search-wrap">
            <i class="fa fa-search w2a-reciters-search-icon" aria-hidden="true"></i>
            <label class="sr-only" for="w2a_anasheed_search_input">ابحث في مقاطع ومواد السلسلة</label>
            <input type="search" id="w2a_anasheed_search_input" class="w2a-reciters-search-input" placeholder="ابحث في مقاطع ومواد السلسلة..." autocomplete="off">
            <button type="button" id="w2a_anasheed_search_clear" class="w2a-reciters-search-clear" hidden aria-label="مسح البحث"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="w2a-tree-badge"><i class="fa fa-video-camera" aria-hidden="true"></i> {{ $items->count() }} مقطع متاح</div>
    </div>

    <div class="w2a-media-items-grid">
        @forelse($items as $item)
            @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
            <a href="/var-item-{{ $item->id }}.htm" class="w2a-media-item-card" data-title="{{ $item->title }}">
                <span class="w2a-media-thumb-wrap">
                    <img src="{{ (int) $item->frame === 1 ? $item->frameThumbUrl() : '/assets/img/defult_shaik.png' }}"
                         class="w2a-media-thumb"
                         alt="{{ $item->title }}"
                         width="320"
                         height="180"
                         loading="lazy"
                         decoding="async">
                    <span class="w2a-media-play-overlay" aria-hidden="true"><span class="w2a-media-play-icon"><i class="fa fa-play"></i></span></span>
                </span>
                <span class="w2a-media-item-body">
                    <span class="w2a-media-item-title">{{ $item->title }}</span>
                    <span class="w2a-media-item-meta">
                        <span class="w2a-media-item-hits"><i class="fa fa-eye" aria-hidden="true"></i> <span>{{ number_format((int) $item->hits) }} مشاهدة</span></span>
                        @if($duration !== '00:00:00')
                            <span class="w2a-media-item-hits"><i class="fa fa-clock-o" aria-hidden="true"></i> <span>{{ $duration }}</span></span>
                        @endif
                    </span>
                    <span class="w2a-media-item-cta"><span>مشاهدة / استماع</span><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                </span>
            </a>
        @empty
            <div class="w2a-tree-empty" role="status">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <h5>عفواً، لا توجد مواد مضافة في هذا القسم</h5>
            </div>
        @endforelse
    </div>
    <p id="w2a_anasheed_result_status" class="sr-only" aria-live="polite"></p>
</div>
