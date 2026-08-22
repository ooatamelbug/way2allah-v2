@extends('layouts.app')

{{--
    Legacy-Source Reconstruction (fatawa-channels.htm): production pretty
    URL (`https://way2allah.com/fatawa-channels.htm`) currently 404s and
    `modules.php` (the `.htaccess:288` dispatcher target) does not exist
    in this snapshot — SOURCE_UNRECOVERABLE for that file. The real
    handler, `fatawa/fatawa-channels.php` (read in full), is directly
    recoverable and its own raw path
    (`https://way2allah.com/fatawa/fatawa-channels.php`) is LIVE —
    LIVE_RENDER_VERIFIED against that path, not the pretty URL.

    fatawa-channels.php:12-13's real document title is
    'عرض الفتاوى | حسب القنوات الفضائية' — a DIFFERENT string from
    $title ('قائمة القنوات الفضائية', used only for the breadcrumb label
    below), confirmed genuinely distinct against the live raw-path fetch.
    Unlike channel_fatawa.php's title, this one does NOT already bake in
    the sitename, so header.php's own unconditional ' - '.$sitename
    append happens only once here (confirmed: title tag ends in a single
    "- الطريق إلى الله", not doubled).
--}}
@section('title', 'عرض الفتاوى | حسب القنوات الفضائية')

{{--
    fatawa-channels.php:14-16 registers fatawa/css/new-style.css (real
    file, confirmed live via `?ver=` cache-busted <link> on the raw
    legacy path) and the Cairo|Reem+Kufi Google Fonts link. Same
    ASSET_UNRECOVERABLE_LOCALLY finding already established for this
    exact file on its sibling page (fatawa-channel-{id}.htm,
    fatawa/topics-index.blade.php's own comment) — no `public/fatawa`
    symlink exists. Only the remote, always-reachable fonts link is added.
--}}
@push('styles')
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    {{--
        fatawa-channels.php:21's page_bar_channels('قائمة القنوات الفضائية')
        — called with ONLY $main, $channel stays '' (default). Verified
        directly: `if($channel)` is a plain truthiness check on '' (always
        false, no PHP-version ambiguity), so the channel <li> branch never
        fires on this page — exactly 3 breadcrumb items (Home, الفتاوى,
        قائمة القنوات الفضائية), confirmed against the live raw-path
        fetch. Same bespoke page_bar_channels() shape as
        fatawa-channel-{id}.htm (icon-before-link, Home's own <li> has no
        trailing separator, no "last item" special case) — NOT the shared
        <x-page-chrome> component, for the same reason established there.
    --}}
    <h1 style=""></h1>
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/"> الرئيسية</a></li><li> <i class="fa fa-angle-right"></i><a href="/fatawa.htm">الفتاوى </a></li><li> <i class="fa fa-angle-right"></i><a href="/fatawa-channels.htm">قائمة القنوات الفضائية</a></li>
        </ul>
    </div>

    {{--
        fatawa-channels.php:26-67's real portlet + channel grid — a
        previously bare <ul><li><a>text</a></li></ul> list, no images, no
        grid, no portlet. Reconstructed exactly from source: outer
        portlet-body carries a genuine legacy typo class, `co-sm-12` (not
        `col-sm-12`) — confirmed present in the live raw-path fetch too,
        reproduced verbatim rather than "corrected" (an inert, harmless
        class name, same byte-parity ethos already applied to other
        confirmed legacy quirks in this migration). Caption text keeps its
        real trailing 3 spaces (source + live-verified). The "بدون قناة"
        (no-channel) entry is unconditional, always first, and — per the
        controller's own already-correct implementation — outside the
        paginated query entirely, matching fatawa-channels.php:38-44
        exactly (unchanged, not touched by this task).

        Pagination: kept as Laravel's `->links()`, matching the
        already-established precedent from this page's own sibling
        (fatawa-channel-{id}.htm) — legacy's own custom pager
        (fatawa/functions.php:708-751) is SOURCE_CONFIRMED but
        deliberately not reproduced, same reasoning as that sibling.
    --}}
    <div class="row service-box margin-bottom-40">
        <div class="col-md-12 col-sm-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"> <i class="fa fa-child"></i>قائمة القنوات الفضائية   </div>
                    </div>

                    <div class="portlet-body col-lg-12 col-md-12 co-sm-12 col-xs-12">
                        {{ $channels->links() }}
                        <div class="portlet-body">
                            <div class="channel_logo col-lg-3 col-md-3 col-sm-6 col-xs-12">
                                <a href="/fatawa-channel-0.htm" class="tt">
                                    <span class="attt" style="width:130px;height:130px;">
                                        <img src="/images/channels/0.png" class="img-responsive center-block" height="120" width="120" alt="بدون قناة">
                                    </span>
                                </a>
                            </div>
                            @foreach ($channels as $channel)
                                <div class="channel_logo col-lg-3 col-md-3 col-sm-6 col-xs-12">
                                    <a href="/fatawa-channel-{{ $channel->id }}.htm" class="tt">
                                        <span class="attt" style="width:130px;height:130px;">
                                            <img src="/images/channels/{{ $channel->id }}.png" class="img-responsive center-block" height="120" width="120" alt="{{ $channel->title }}">
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        {{ $channels->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
