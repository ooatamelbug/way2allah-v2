@extends('layouts.app')

{{-- Premium channel directory; document title and route contracts remain unchanged. --}}
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

@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
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
            <li><i class="fa fa-home"></i><a href="/"> الرئيسية</a></li>
            <li> <i class="fa fa-angle-right"></i><a href="/fatawa.htm">الفتاوى </a></li>
            <li> <i class="fa fa-angle-right"></i><a href="/fatawa-channels.htm">قائمة القنوات الفضائية</a></li>
        </ul>
    </div>

    {{-- The unconditional no-channel entry remains first; paginated channel data follows. --}}
    <div class="w2a-refresh-page w2a-fatawa-channels-page">
        <x-content.premium-panel title="قائمة القنوات الفضائية" icon="fa-television"
            description="تصفّح الفتاوى حسب القناة الفضائية التي عُرضت عليها.">
            <div class="w2a-channel-grid">
                <a href="/fatawa-channel-0.htm" class="w2a-channel-card">
                    <span class="w2a-channel-card__logo">
                        <img src="/images/channels/0.png" width="120" height="120" alt="بدون قناة" decoding="async">
                    </span>
                    <span class="w2a-channel-card__content">
                        <strong>فتاوى بدون قناة</strong>
                        <span>استعرض الفتاوى <i class="fa fa-angle-right" aria-hidden="true"></i></span>
                    </span>
                </a>
                @foreach ($channels as $channel)
                    <a href="/fatawa-channel-{{ $channel->id }}.htm" class="w2a-channel-card">
                        <span class="w2a-channel-card__logo">
                            <img src="/images/channels/{{ $channel->id }}.png" width="120" height="120"
                                alt="{{ $channel->title }}" loading="lazy" decoding="async">
                        </span>
                        <span class="w2a-channel-card__content">
                            <strong>{{ $channel->title }}</strong>
                            <span>استعرض الفتاوى <i class="fa fa-angle-right" aria-hidden="true"></i></span>
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="w2a-refresh-pagination" aria-label="صفحات القنوات">
                {{ $channels->links() }}
            </div>
        </x-content.premium-panel>
    </div>
@endsection
