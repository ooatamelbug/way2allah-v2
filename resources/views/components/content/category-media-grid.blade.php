@props(['items'])

<div class="w2a-cat-items-wrap">
    <div class="w2a-cat-items-toolbar">
        <div class="w2a-gallery-search-wrap" style="flex: 1; max-width: 380px;">
            <i class="fa fa-search w2a-gallery-search-icon" aria-hidden="true"></i>
            <label class="sr-only" for="w2a_cat_items_search_input">ابحث في قائمة المواد</label>
            <input type="search" id="w2a_cat_items_search_input" class="w2a-gallery-search-input" placeholder="ابحث في قائمة المواد..." autocomplete="off">
            <button type="button" id="w2a_cat_items_search_clear" class="w2a-gallery-search-clear" hidden aria-label="مسح البحث">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="w2a-tree-badge"><i class="fa fa-film" aria-hidden="true"></i> {{ count($items) }} مادة</div>
    </div>

    <div class="w2a-cat-items-grid">
        @foreach ($items as $item)
            @php
                $authorName = trim(($item->prename ?? '').' '.($item->name ?? ''));
                $duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0));
            @endphp
            <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-cat-media-card" data-title="{{ trim((string) $item->title) }}" data-author="{{ $authorName }}">
                <div class="w2a-cat-media-header">
                    @if ($authorName !== '')
                        <span class="w2a-cat-media-author"><i class="fa fa-user" aria-hidden="true"></i> {{ $authorName }}</span>
                    @endif
                </div>
                <h3 class="w2a-cat-media-title">{{ trim((string) $item->title) }}</h3>
                <div class="w2a-cat-media-meta">
                    <span class="w2a-cat-media-meta-item"><i class="fa fa-calendar" aria-hidden="true"></i> {{ ! empty($item->time) ? date('Y-m-d', (int) $item->time) : '' }}</span>
                    <span class="w2a-cat-media-meta-item"><i class="fa fa-eye" aria-hidden="true"></i> {{ number_format((int) $item->hits) }}</span>
                    @if ($duration !== '00:00:00')
                        <span class="w2a-cat-media-meta-item"><i class="fa fa-clock-o" aria-hidden="true"></i> {{ $duration }}</span>
                    @endif
                    <span class="w2a-cat-media-action">
                        <span>استماع / مشاهدة</span>
                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                    </span>
                </div>
            </a>
        @endforeach
    </div>
    <p id="w2a_cat_items_result_status" class="sr-only" aria-live="polite"></p>
</div>
