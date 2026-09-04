@extends('layouts.app')

{{-- Shared Page Chrome Parity Audit: series.php:36's real document title includes the author suffix, same string as the visible heading. --}}
@section('title', $pageHeading)

@section('content')
    <x-page-chrome :heading="$pageHeading" :breadcrumb="$breadcrumbTrail" />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                khotab-series-{id}.htm parity: khotab/series.php calls the
                shared khotab/functions.php ListKhotab($ob, 'قائمة المواد', $hidden)
                — $ob->mode is never set, so the "default" branch (khotabItemsDefault(),
                already used unchanged) renders. Row markup matches that branch
                exactly: no author link (mode isn't fixed/new/day), date shown,
                comments shown, hits shown, conditional channel badge, conditional
                duration — same pattern already established/tested for
                khotab-author.blade.php and categories/show.blade.php.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.khotab-item-list :items="$items" :video="(bool) $seriesModel->vedio" />
                    </div>
                </div>
            </div>

            @if(!empty($seriesModel->description))
                <section aria-label="وصف السلسلة">{{ $seriesModel->description }}</section>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{-- G-13-03: series.php:118-126 — "الملف الشخصي" box, the series'
                 own author (series.php:31's $Series->author_id), unconditional
                 get_author_img() (no author_image DB-column check, unlike
                 author.php/authors.php). --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>
                    </div>
                    <div class="portlet-body">
                        <div class="profile-userpic">
                            <img src="{{ $seriesModel->author?->fallbackImageUrl() }}" alt="">
                        </div>
                    </div>
                </div>
            </div>

            {{--
                series.php:136-137's randomitems() (functions.php:1092-1141) —
                a `.thumbnail`/`.caption` card, NOT a list: image (gif/frame
                branch, bucketed path, no file_exists() gate — legacy always
                builds the URL unconditionally), author name as <h3>, title
                link as <p><a>. Verified against fresh live khotab-series-2.htm
                HTML this batch — confirmed this is not the plain-<li> markup
                previously assumed/copied from khotab-author.blade.php.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        @foreach ($randomFeatured as $item)
                            @php($photo = ((int) ($item->gif ?? 0)) === 1
                                ? \App\Domain\Content\Support\MediaPathResolver::path('khotab_gifs', $item->id, 'gif')
                                : \App\Domain\Content\Support\MediaPathResolver::path('khotab_frames', $item->id, 'jpg'))
                            <div class="thumbnail">
                                <img src="/{{ $photo }}" alt="{{ $item->title }}" style="width: 100%; height: 160px; display: block;">
                                <div class="caption">
                                    <h3>{{ $item->name }}</h3>
                                    <p><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{--
                series.php:140-156's two topitems('hits', ..., $orderby, 5) calls
                (functions.php:992-1090) — both pass mode='hits' regardless of
                sort order, so BOTH boxes show "عدد مرات التحميل: X مرة", never
                a date, even for "جديد المواد" (confirmed against fresh live
                HTML: its <small> tags read the download count, not a date,
                despite being sorted by time DESC). Thumbnail link is legacy's
                own dead `href="javascript:;"` (functions.php:1065) — reproduced
                as observed, not "fixed" to link anywhere.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostDownloaded" />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostRecent" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
