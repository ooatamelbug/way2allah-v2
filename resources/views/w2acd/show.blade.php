@extends('layouts.app')

{{--
    `cds-item-{id}.htm` Legacy-Source Design Reconstruction. `w2acd/item.php`
    (54 lines) re-read in full, plus `w2acd/functions.php`'s
    `w2acd_details()`/`list_w2acd_mirrors()`/`most_downloaded_recent_sidebar()`
    (functions.php:85-246) — confirmed via a live raw fetch of
    `w2acd/item.php?khid=44` (LIVE_RENDER_VERIFIED, no source drift).

    Unlike `cds-main.htm` (single full-width column, no sidebar), THIS
    page genuinely HAS both a two-column layout AND the
    `most_downloaded_recent_sidebar($Group)` sidebar — confirmed by a
    direct, independent re-read (not inferred from cds-main.htm's own,
    different, no-sidebar structure).

    IF-044 (`cds-series-{id}.grx`/GetRight): confirmed NOT referenced
    anywhere in `item.php` or any function it calls — genuinely unrelated
    to this page, not implemented.

    Confirmed legacy bug/date-format gap, corrected here (was plain
    `date('Y-m-d', ...)`): `w2acd_details()`'s own "تاريخ التحميل" row
    and `most_recent_html()`'s own "بتاريخ" sidebar label both call
    `CoolShortDate($item->mytime)` — the full Arabic-day/month format,
    confirmed live (`السبت 6 يونيو 2015 مـ`), not `Y-m-d`.

    Already correct, untouched: the hidden-gated image gallery
    (h=400&w=400&zc=0&q=100, first image sized 350x400 vs 400x400 for the
    rest), the mirror extension/"سيرفر خاص" classification and save
    column, and the sidebar's `cds-item-{id}.htm` links (a confirmed,
    already-approved fix — P-016 §2 — over legacy's own `var-item-{id}.htm`
    bug, confirmed still present in the raw fetch; NOT reverted here).
--}}
@section('title', $w2acdItem->title)

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush

@section('content')
    <x-page-chrome
        :heading="$w2acdItem->title"
        :breadcrumb="[['title' => 'الاسطوانات الدعوية', 'url' => '/cds-main.htm'], ['title' => $w2acdItem->title, 'url' => '']]"
    />

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-desktop"></i> تفاصيل الاسطوانة</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="anasheed-details mada-details">
                            <table class="table table-striped">
                                <tr>
                                    <th class="w20" style="border-top:0;">عنوان الاسطوانة</th>
                                    <td style="border-top:0;">{{ $w2acdItem->title }}</td>
                                </tr>
                                <tr>
                                    <th class="w20">تاريخ التحميل</th>
                                    <td>{{ $w2acdItem->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $w2acdItem->mytime) : '' }}</td>
                                </tr>
                                <tr>
                                    <th class="w20">عدد الزيارات</th>
                                    <td>{{ $w2acdItem->hits }} زيارة</td>
                                </tr>
                            </table>
                            <br /><br />
                            @if ($w2acdItem->hidden == 0)
                                <div class="row text-center jumbotron-icon">
                                    @foreach ($w2acdItem->thumbnailFilenames() as $filename)
                                        @php($imgUrl = \App\Domain\Content\Support\MediaUrl::thumbnail('h=400&w=400&zc=0&q=100&src=/images/cds_image2/'.$filename))
                                        @if ($loop->first)
                                            <div class="cd_first_img">
                                                <img src="{{ $imgUrl }}" height="350" width="400" title="{{ $w2acdItem->title }}" alt="{{ $w2acdItem->title }}" />
                                            </div>
                                        @else
                                            <div class="cd_first_img"><img src="{{ $imgUrl }}" height="400" width="400"/></div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-link"></i> روابط الاسطوانة</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="anasheed-mirrors table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th width="10%">م</th>
                                    <th>الوصف</th>
                                    <th class="">الإمتداد</th>
                                    <th class="">حفظ</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($w2acdItem->mirrorLinks() as $index => $mirror)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><a href="{{ $mirror['link'] }}" target="_blank">{{ $mirror['title'] }}</a></td>
                                        <td>
                                            @if ($mirror['isPrivateServer'])
                                                سيرفر خاص
                                            @else
                                                <img src="/images/ext/{{ $mirror['extension'] }}.gif" alt="نوع الملف {{ $mirror['extension'] }}" border="0" />
                                            @endif
                                        </td>
                                        <td>
                                            @if ($mirror['extension'] === '')
                                                <img height="20" width="20" src="/images/2.png" title="حفظ" alt="حفظ">
                                            @else
                                                <a href="{{ $mirror['link'] }}" target="_blank"><img height="20" width="20" src="/images/save.png" title="حفظ" alt="حفظ"></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-5 nopadding">
            @if ($mostDownloaded->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue top_side">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                        </div>
                        <div class="portlet-body ">
                            <ul class="recent_list">
                                @foreach ($mostDownloaded as $item)
                                    @php($sideThumb = $item->firstThumbnailFilename() ? '/images/cds_image2/'.$item->firstThumbnailFilename() : '/images/tvnoise.gif')
                                    <li class="list-group-item anasheed-latest-item">
                                        <div class="row">
                                            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-4">
                                                <a href="/cds-item-{{ $item->id }}.htm"> <img src="{{ $sideThumb }}" class="img-responsive img-thumbnail" alt="{{ $item->title }}" /></a>
                                            </div>
                                            <div class="col-lg-9 col-md-9 col-sm-9 col-xs-8">
                                                <a href="/cds-item-{{ $item->id }}.htm"> <h6>{{ $item->title }}</h6></a>
                                                <small>مرات التحميل : {{ $item->hits }} مرة</small>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @if ($mostRecent->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue top_side">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-flash"></i> احدث المواد</div>
                        </div>
                        <div class="portlet-body ">
                            <ul class="recent_list">
                                @foreach ($mostRecent as $item)
                                    @php($sideThumb = $item->firstThumbnailFilename() ? '/images/cds_image2/'.$item->firstThumbnailFilename() : '/images/tvnoise.gif')
                                    <li class="list-group-item anasheed-latest-item">
                                        <div class="row">
                                            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-4">
                                                <a href="/cds-item-{{ $item->id }}.htm"> <img src="{{ $sideThumb }}" class="img-responsive img-thumbnail" alt="{{ $item->title }}" /></a>
                                            </div>
                                            <div class="col-lg-9 col-md-9 col-sm-9 col-xs-8">
                                                <a href="/cds-item-{{ $item->id }}.htm"> <h6>{{ $item->title }}</h6></a>
                                                <small>بتاريخ : {{ $item->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->mytime) : '' }}</small>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
