@extends('layouts.app')

{{-- channels/channels.php. $panelTitle is deliberately blank — IF-011,
     the legacy panel title comes from an undefined $Anasheed variable. --}}

@section('title', 'قائمة القنوات الفضائية')

{{--
    channels.htm parity: channels.php:9's $header['css']['custom'] injects
    <link href="assets/frontend/pages/css/gallery.css"> unconditionally,
    echoed raw by header.php:139. Confirmed genuinely necessary, not
    redundant: assets/frontend/layout/css/custom.css (already loaded
    sitewide) only carries 3 narrow *override* rules for .channel-logo/
    .gallery-item (sizing, an opacity/background !important on
    .channel-logo .zoomix) — it never defines the base hover-reveal
    mechanism itself (position:absolute, opacity:0, transform:scale(0),
    transition, the a:hover trigger). Without gallery.css, .zoomix would
    render as a normal in-flow block, not the hidden-until-hover overlay
    channels.php's own markup (.gallery-item/.zoomix/.channel-logo,
    channels.php:41-51) is built for — ASSET_PRESENT alone would not have
    been ASSET_EFFECTIVE without restoring that markup too.
--}}
@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush

@section('content')
    {{-- Shared Page Chrome Parity Audit: channels.php:19-21 — single-item breadcrumb (current page, empty href), heading "قائمة القنوات الفضائية". --}}
    <x-page-chrome heading="قائمة القنوات الفضائية" :breadcrumb="[['title' => 'القنوات الفضائية', 'url' => '']]" />

    <section aria-label="{{ $panelTitle }}">
        <div style="clear: both"></div>
        @foreach ($channels as $channel)
            <div class="col-md-3 col-sm-4 col-xs-6 gallery-item">
                <a data-rel="fancybox-button" href="/channel-{{ $channel->id }}.htm" class="fancybox-button channel-logo">
                    <img alt="" src="/images/channels/{{ $channel->id }}.png" class="img-responsive" width="100%">
                    <div class="zoomix">
                        قناة : {{ $channel->title }}
                        <br>النايل سات 7 غرباً<br>التردد : {{ $channel->freq }}
                        <br>الإستقطاب : {{ $channel->polar }}
                        <br>معدل الترميز : {{ $channel->srate }}
                        <br>معامل التصويب : {{ $channel->fec }}
                    </div>
                </a>
            </div>
        @endforeach
        <div style="clear: both"></div>
    </section>
@endsection
