@props(['items', 'type' => 'series', 'secondary' => null])

@php
    $isGroup = $type === 'group';
    $icon = $isGroup ? 'fa-folder' : 'fa-folder-open-o';
    $label = $isGroup ? 'المجموعة' : 'السلسلة';
@endphp

<div class="w2a-cat-series-grid">
    @foreach ($items as $item)
        @php
            $secondaryText = match ($secondary) {
                'author' => trim((string) ($item->author ?? '')),
                'channel' => trim((string) ($item->channel ?? '')),
                default => '',
            };
            $secondaryIcon = $secondary === 'channel' ? 'fa-television' : 'fa-user-circle-o';
        @endphp
        <a href="/khotab-{{ $type }}-{{ $item->id }}.htm" class="w2a-cat-series-card">
            <div class="w2a-cat-series-header">
                <span class="w2a-cat-series-icon-wrap"><i class="fa {{ $icon }}" aria-hidden="true"></i></span>
                <span class="w2a-cat-series-count"><i class="fa fa-play-circle" aria-hidden="true"></i> {{ number_format((int) $item->count) }} مادة</span>
            </div>
            <div class="w2a-cat-series-body">
                <h3 class="w2a-cat-series-title">{{ trim((string) $item->title) }}</h3>
                @if ($secondaryText !== '')
                    <span class="w2a-cat-series-author"><i class="fa {{ $secondaryIcon }}" aria-hidden="true"></i> {{ $secondaryText }}</span>
                @endif
                <span class="w2a-cat-series-cta">
                    <span>تصفح {{ $label }}</span>
                    <i class="fa fa-angle-left" aria-hidden="true"></i>
                </span>
            </div>
        </a>
    @endforeach
</div>
