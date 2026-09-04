@props(['items', 'mode' => 'hits'])

<ul class="media-list">
    @foreach($items as $item)
        <li class="media w2a-top-item">
            <a class="pull-left w2a-top-item-thumb-link" href="/khotab-item-{{ $item->id }}.htm">
                <img class="media-object w2a-top-item-thumb"
                     src="{{ $item->thumb ?? '/images/way2_withoutimg.png' }}"
                     alt="{{ $item->title }}"
                     width="60"
                     height="40"
                     loading="lazy"
                     decoding="async">
            </a>
            <div class="media-body w2a-top-item-body">
                <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-top-item-title-link">
                    <h5 class="media-heading w2a-top-item-title">{{ $item->title }}</h5>
                </a>
                @if($mode === 'time')
                    <span class="w2a-top-item-badge"><i class="fa fa-clock-o" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->time) }}</span>
                @else
                    <span class="w2a-top-item-badge"><i class="fa fa-cloud-download" aria-hidden="true"></i> {{ number_format($item->hits) }} تحميل</span>
                @endif
            </div>
        </li>
    @endforeach
</ul>
