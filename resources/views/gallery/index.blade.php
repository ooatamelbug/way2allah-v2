@extends('layouts.app')

@section('title', 'التصميمات الدعوية')

@section('content')
    <section aria-label="التصميمات الدعوية">
        <div class="row">
            @foreach ($albums as $album)
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                    {{--
                        G-03 (Migration Gap Register): `gallery/list.php:38`'s
                        `thumbnails.php?h=250&w=350&src=...` — reproduces the
                        exact legacy URL shape (G-02's own precedent: build
                        the identical relative URL, no new resize code,
                        thumbnails.php remains legacy-served per ADR-0001).
                    --}}
                    @php $thumbUrl = '/thumbnails.php?h=250&w=350&src='.optional($album->thumbnailImage())->url; @endphp
                    <a href="/gallery-{{ $album->album_id }}.htm">
                        <img src="{{ $thumbUrl }}" alt="{{ $album->title }}" class="img-responsive">
                        <h5>{{ $album->title }}</h5>
                    </a>
                    {{-- `last_update` is a unix timestamp (Album's own @property) — plain date(), not a CoolShortDate() port, matching this project's already-established convention (khotab/item.blade.php). --}}
                    <span><i class="fa fa-calendar"></i> اخر تحديث : {{ $album->last_update ? date('Y-m-d', $album->last_update) : '' }}</span>
                    <span>{{ $album->count }} صورة</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
