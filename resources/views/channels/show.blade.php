@extends('layouts.app')

{{-- channels/channel.php. Note: no hardcoded domain in the channel logo
     path (legacy hardcodes http://way2allah.com/..., P-018, a confirmed
     anti-pattern already cataloged elsewhere in the audit — not
     reproduced here). --}}

{{--
    Batch 1 (category-1.htm/channel-1.htm parity): channels/channel.php:19-20
    (`register_script('scripts/khotab_tables.js'); Plugins('datatables');`) —
    confirmed unconditional. channels/author.php (author.blade.php's source)
    does NOT call these — confirmed zero matches — so this push stays only
    on this view, not on the shared _listing partial or author.blade.php.
--}}
@push('styles')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap-rtl.css" rel="stylesheet" type="text/css"/>
@endpush

@push('scripts')
    <script src="/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="/scripts/khotab_tables.js" type="text/javascript"></script>
@endpush

{{--
    Shared Page Chrome Parity Audit: channels/channel.php:12's real document
    title is `'مرئيات قناة ' . $Channel->title` — a "مرئيات" prefix the
    visible heading (`title('قناة ' . $Channel->title)`, no prefix) does
    NOT carry, confirmed as two genuinely different strings against fresh
    production (this page's own no-author branch; author.blade.php's
    corresponding page is untouched, out of this batch's scope).
--}}
@section('title', 'مرئيات قناة ' . $channelModel->title)

@section('content')
    {{--
        Shared Page Chrome Parity Audit: channels/channel.php:41-46's
        no-author branch — `title('قناة ' . $Channel->title)` +
        `breadcrumb([{القنوات الفضائية, url}, {قناة .. , url=''}])`.
    --}}
    <x-page-chrome
        :heading="'قناة '.$channelModel->title"
        :breadcrumb="[
            ['title' => 'القنوات الفضائية', 'url' => '/channels.htm'],
            ['title' => 'قناة '.$channelModel->title, 'url' => ''],
        ]"
    />

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
