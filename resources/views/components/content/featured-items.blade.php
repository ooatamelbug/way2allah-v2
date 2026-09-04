@props(['items'])

@foreach ($items as $item)
    @php
        $photo = ((int) ($item->gif ?? 0)) === 1
            ? \App\Domain\Content\Support\MediaPathResolver::path('khotab_gifs', $item->id, 'gif')
            : \App\Domain\Content\Support\MediaPathResolver::path('khotab_frames', $item->id, 'jpg');
        $authorName = trim(($item->prename ?? '').' '.($item->name ?? ''));
    @endphp
    <article class="w2a-featured-item-card">
        <div class="w2a-featured-img-wrap">
            <img src="/{{ $photo }}" alt="{{ $item->title }}" class="w2a-featured-img" width="320" height="180" loading="lazy" decoding="async">
            <span class="w2a-featured-badge"><i class="fa fa-star" aria-hidden="true"></i> مادة مختارة</span>
        </div>
        <div class="w2a-featured-body">
            @if ($authorName !== '')
                <span class="w2a-featured-author"><i class="fa fa-user" aria-hidden="true"></i> {{ $authorName }}</span>
            @endif
            <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-featured-title">{{ $item->title }}</a>
            <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-featured-btn">
                <span>استمع / شاهد الآن</span>
                <i class="fa fa-angle-right" aria-hidden="true"></i>
            </a>
        </div>
    </article>
@endforeach
