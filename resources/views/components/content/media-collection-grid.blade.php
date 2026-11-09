@props(['items', 'type' => 'series', 'secondary' => null, 'rowControls' => null, 'markHidden' => false, 'secondaryHref' => null])
{{-- rowControls: an admin-only callable(item): list<ContextualControl>, rendered beside each
     card (a form cannot sit inside the card's <a>). Null — always, for visitors — leaves the
     markup as before.
     markHidden: OLD's «++مخفي++» after the count of a row whose `hidden` is 1. Only admin
     queries select `hidden`, so a visitor's markup is unchanged.
     secondaryHref: an optional callable(item): ?string giving the secondary text its own link
     (Gen 2 channel listings link the author, channels/functions.php:48, :137). When it returns
     a URL, the card is an <article> whose title link is stretched over the card (so the whole
     card still opens the group / series) and the secondary link sits above it — no nested
     <a>. Null (the default) or a null URL keeps the original single-<a> card markup. --}}

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
            $secondaryUrl = ($secondaryHref !== null && $secondaryText !== '') ? $secondaryHref($item) : null;
        @endphp
        @if ($rowControls !== null)
            <div class="w2a-admin-row-wrap">
        @endif
@if (is_string($secondaryUrl) && $secondaryUrl !== '')
        <article class="w2a-cat-series-card w2a-cat-series-card--linked">
            <div class="w2a-cat-series-header">
                <span class="w2a-cat-series-icon-wrap"><i class="fa {{ $icon }}" aria-hidden="true"></i></span>
                <span class="w2a-cat-series-count"><i class="fa fa-play-circle" aria-hidden="true"></i>
                    {{ number_format((int) $item->count) }} مادة{{ $markHidden && (int) ($item->hidden ?? 0) === 1 ? ' ++مخفي++' : '' }}</span>
            </div>
            <div class="w2a-cat-series-body">
                <h3 class="w2a-cat-series-title"><a href="/khotab-{{ $type }}-{{ $item->id }}.htm" class="w2a-cat-series-card__link">{{ trim((string) $item->title) }}</a></h3>
                <a href="{{ $secondaryUrl }}" class="w2a-cat-series-author w2a-cat-series-card__secondary"><i class="fa {{ $secondaryIcon }}" aria-hidden="true"></i>
                    {{ $secondaryText }}</a>
                <span class="w2a-cat-series-cta" aria-hidden="true">
                    <span>تصفح {{ $label }}</span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </span>
            </div>
        </article>
@else
        <a href="/khotab-{{ $type }}-{{ $item->id }}.htm" class="w2a-cat-series-card">
            <div class="w2a-cat-series-header">
                <span class="w2a-cat-series-icon-wrap"><i class="fa {{ $icon }}" aria-hidden="true"></i></span>
                <span class="w2a-cat-series-count"><i class="fa fa-play-circle" aria-hidden="true"></i>
                    {{ number_format((int) $item->count) }} مادة{{ $markHidden && (int) ($item->hidden ?? 0) === 1 ? ' ++مخفي++' : '' }}</span>
            </div>
            <div class="w2a-cat-series-body">
                <h3 class="w2a-cat-series-title">{{ trim((string) $item->title) }}</h3>
                @if ($secondaryText !== '')
                    <span class="w2a-cat-series-author"><i class="fa {{ $secondaryIcon }}" aria-hidden="true"></i>
                        {{ $secondaryText }}</span>
                @endif
                <span class="w2a-cat-series-cta">
                    <span>تصفح {{ $label }}</span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </span>
            </div>
        </a>
@endif
        @if ($rowControls !== null)
                <x-admin.contextual-controls variant="inline" :controls="$rowControls($item)" />
            </div>
        @endif
    @endforeach
</div>
