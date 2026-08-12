@extends('layouts.app')

{{-- channels/channel.php. Note: no hardcoded domain in the channel logo
     path (legacy hardcodes http://way2allah.com/..., P-018, a confirmed
     anti-pattern already cataloged elsewhere in the audit — not
     reproduced here). --}}

@section('title', 'قناة ' . $channelModel->title)

@section('content')
    {{-- Live-Reference Comparison Report: breadcrumb + portlet wrappers + the same row/col grid already used on fatawa/khotab-item. No data/query change. --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/channels.htm">القنوات الفضائية</a><i class="fa fa-angle-right"></i></li>
            <li>قناة {{ $channelModel->title }}</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            @include('channels._listing', ['showAuthorLinks' => true])
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">بيانات القناة</div>
                    </div>
                    <div class="portlet-body">
                        <img src="/images/channels/{{ $channelModel->id }}.png" alt="{{ $channelModel->title }}">
                        <p>التردد: {{ $channelModel->freq }}</p>
                        <p>الإستقطاب: {{ $channelModel->polar }}</p>
                        <p>معدل الترميز: {{ $channelModel->srate }}</p>
                        <p>معامل التصويب: {{ $channelModel->fec }}</p>
                        <p>التشفير: {{ $channelModel->enc }}</p>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostDownloaded as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostRecent as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
