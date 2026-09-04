@props(['items'])

<div class="w2a-items-list-wrap">
    @foreach ($items as $item)
        <article class="w2a-item-card-row">
            <span class="w2a-item-icon-badge" aria-hidden="true"><i class="fa fa-folder-open-o"></i></span>
            <div class="w2a-item-card-content">
                <div class="w2a-item-card-header">
                    <a href="/khotab-series-{{ $item->id }}.htm" class="w2a-item-card-title">{{ trim((string) $item->title) }}</a>
                </div>
                <div class="w2a-item-card-meta">
                    @if (! empty($item->time))
                        <span class="w2a-meta-pill"><i class="fa fa-calendar" aria-hidden="true"></i> {{ date('Y-m-d', (int) $item->time) }}</span>
                    @endif
                    <span class="w2a-meta-pill"><i class="fa fa-play-circle-o" aria-hidden="true"></i> المواد: {{ number_format((int) $item->count) }}</span>
                </div>
            </div>
            <a href="/khotab-series-{{ $item->id }}.htm" class="w2a-item-action-btn">
                <span>عرض السلسلة</span>
                <i class="fa fa-angle-right" aria-hidden="true"></i>
            </a>
        </article>
    @endforeach
</div>
