@props([
    'author',
    'href',
    'count',
    'countLabel',
])

@php($displayName = trim($author->prename.' '.$author->name))

<a href="{{ $href }}" class="w2a-preacher-card" data-name="{{ $displayName }}">
    <span class="w2a-preacher-avatar-wrap">
        <img class="w2a-preacher-avatar" src="{{ $author->displayImageUrl() }}" alt="صورة {{ $displayName }}" width="72" height="72" loading="lazy" decoding="async">
    </span>
    <span class="w2a-preacher-info">
        <span class="w2a-preacher-name">{{ $displayName }}</span>
        <span class="w2a-preacher-count">{{ $count }} {{ $countLabel }}</span>
    </span>
    <i class="fa fa-angle-left w2a-preacher-arrow" aria-hidden="true"></i>
</a>
