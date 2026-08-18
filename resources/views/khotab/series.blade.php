@extends('layouts.app')

@section('title', 'سلسلة ' . $seriesModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="قائمة المواد">
                <ul>
                    @foreach ($items as $item)
                        <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </section>

            @if(!empty($seriesModel->description))
                <section aria-label="وصف السلسلة">{{ $seriesModel->description }}</section>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{-- G-13-03: series.php:118-126 — "الملف الشخصي" box, the series'
                 own author (series.php:31's $Series->author_id), unconditional
                 get_author_img() (no author_image DB-column check, unlike
                 author.php/authors.php). --}}
            <h3>الملف الشخصي</h3>
            <div class="profile-userpic">
                <img src="{{ $seriesModel->author?->fallbackImageUrl() }}" alt="">
            </div>

            <h3>اخترنا لك هذه المادة</h3>
            <ul>
                @foreach ($randomFeatured as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li class="media">
                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                    </li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li class="media">
                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
