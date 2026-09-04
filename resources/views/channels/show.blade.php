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
            {{--
                Final Sidebar Gap Closure (2026-08-22): channel.php:70-89's
                "بيانات القناة" box — fa-child icon (w2a_open_div()'s own
                argument, re-read fresh), real `.thumbnail`/`.caption`
                structure with an `<h3>` title, and 2 legacy-hardcoded
                lines ("القمر الصناعي : النايل سات" / "الموقع المداري : 7
                غرباً") that are NOT DB fields — confirmed literal strings
                in channel.php:81-82, not $Channel-> properties. The logo
                `<img>` intentionally stays relative (/images/channels/{id}.png),
                not legacy's hardcoded http://way2allah.com/... domain
                (P-018, already-established anti-pattern, not reproduced).
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> بيانات القناة</div>
                    </div>
                    <div class="portlet-body">
                        <div class="thumbnail">
                            <img src="/images/channels/{{ $channelModel->id }}.png" alt="{{ $channelModel->title }}" style="width: 100%; height: 200px; display: block;">
                            <div class="caption">
                                <h3>قناة {{ $channelModel->title }}</h3>
                                <p>القمر الصناعي : النايل سات</p>
                                <p>الموقع المداري : 7 غرباً</p>
                                <p>التردد : {{ $channelModel->freq }}</p>
                                <p>الإستقطاب : {{ $channelModel->polar }}</p>
                                <p>معدل الترميز : {{ $channelModel->srate }}</p>
                                <p>معامل التصويب : {{ $channelModel->fec }}</p>
                                <p>التشفير : {{ $channelModel->enc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{--
                channel.php:94-101 — topitems('hits', "channel_id='X' and
                vedio='1'", "hits DESC", 5), fa-cloud-download icon. Real
                functions.php:992-1090 media-list card DOM: 60x40 thumb
                (topitemsThumb(), the already-verified G-13-01 helper — the
                author-photo fallback branch is a confirmed deterministic
                legacy bug, never a real lookup), <h5> title link to
                khotab-item-{id}.htm, hits metadata (mode='hits').
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostDownloaded" />
                    </div>
                </div>
            </div>

            {{--
                channel.php:103-111 — topitems('time', ..., "time DESC", 5),
                fa-flash icon. mode='time' confirmed directly from source
                (not assumed) — real date metadata, not a hit count.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostRecent" mode="time" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
