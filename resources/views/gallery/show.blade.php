@extends('layouts.app')

@section('title', 'التصميمات الدعوية - ' . $albumModel->title)

{{--
    G-03 (Migration Gap Register): `gallery/item.php:62-63`'s two
    thumbnails.php URL shapes (grid thumb 150x166, lightbox target
    w=500 only — no h, no zc, matching legacy's own proportional-scale
    call exactly) plus the legacy lightbox viewer
    (`jquery.lightbox-0.4.js` + blockUI), previously non-functional in
    this port (no JS was ever loaded for it). Assets reachable via the
    new public/gallery/lightbox symlink (mirrors the existing
    public/assets -> legacy-project/assets pattern). Page-scoped here,
    not in the shared layout — same "no @stack, load inline" approach
    as G-02's carouFredSel wiring.
--}}
@section('content')
    <link href="/gallery/lightbox/jquery.lightbox-0.4.css" rel="stylesheet" type="text/css">
    <section aria-label="ألبوم الصور">
        <h1>ألبوم : {{ $albumModel->title }}</h1>

        @if($images->isEmpty())
            <p>عفوا! لا يوجد صور مضافة في هذا الالبوم بعد.</p>
        @else
            <div class="row">
                @foreach ($images as $image)
                    @php
                        $thumbUrl = '/thumbnails.php?h=150&w=166&src='.$image->url;
                        $fullUrl = '/thumbnails.php?w=500&src='.$image->url;
                    @endphp
                    <div class="col-lg-3 col-md-3 col-sm-4 col-xs-6">
                        <a href="{{ $fullUrl }}" class="lightbox" rel="album{{ $albumModel->album_id }}">
                            <img src="{{ $thumbUrl }}" alt="{{ $albumModel->title }}" class="img-responsive pwimages">
                        </a>
                        <a href="/albumimg-download-{{ $image->image_id }}.htm">حفظ الصورة</a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    <script src="/gallery/lightbox/jquery.blockUI-1.33.pack.js"></script>
    <script src="/gallery/lightbox/jquery.lightbox-0.4.pack.js"></script>
    <script>
        $(document).ready(function () {
            $('a.lightbox').lightBox();
        });
    </script>
@endsection
