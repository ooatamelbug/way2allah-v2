@extends('layouts.app')

@section('title', 'قسم الاسطوانات الدعوية')

{{--
    G-04 (Migration Gap Register): `w2acd/functions.php:23-29`'s listing
    thumbnail (`thumbnails.php?h=104&w=105&src=...`, `way2_cddefault.png`
    fallback — also thumbnails.php-wrapped) and `functions.php:212-242`'s
    sidebar (raw, NOT thumbnails.php-wrapped, `tvnoise.gif` fallback +
    download-count/date subtext). `gallery.css` already reachable via the
    existing public/assets symlink — no new symlink needed.
--}}
@section('content')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="قائمة الإسطوانات">
                <div class="row">
                    @foreach ($items as $item)
                        @php
                            $listPhoto = $item->firstThumbnailFilename()
                                ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                                : '/images/way2_cddefault.png';
                            $listThumb = '/thumbnails.php?h=104&w=105&src='.$listPhoto;
                        @endphp
                        <div>
                            <a href="/cds-item-{{ $item->id }}.htm">
                                <img src="{{ $listThumb }}" alt="{{ $item->title }}">
                                <span>{{ $item->title }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
                {{ $items->links() }}
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    @php
                        $sideThumb = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                            : '/images/tvnoise.gif';
                    @endphp
                    <li>
                        <a href="/cds-item-{{ $item->id }}.htm">
                            <img src="{{ $sideThumb }}" alt="{{ $item->title }}">
                            <span>{{ $item->title }}</span>
                            <small>مرات التحميل : {{ $item->hits }} مرة</small>
                        </a>
                    </li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    @php
                        $sideThumb = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                            : '/images/tvnoise.gif';
                    @endphp
                    <li>
                        <a href="/cds-item-{{ $item->id }}.htm">
                            <img src="{{ $sideThumb }}" alt="{{ $item->title }}">
                            <span>{{ $item->title }}</span>
                            {{-- mytime -> plain date(), NOT a CoolShortDate() port, matching this project's established convention. --}}
                            <small>بتاريخ : {{ $item->mytime ? date('Y-m-d', $item->mytime) : '' }}</small>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
