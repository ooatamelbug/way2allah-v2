@extends('layouts.app')

{{--
    Legacy-Source Reconstruction (social.htm): production pretty URL 404s
    (LEGACY_PRETTY_URL_ORPHANED — header.php:340,347's real, unconditional
    account-dropdown nav links to "social.htm" exist, but no .htaccess
    rule was ever added). `pages/social.php`'s own raw path
    (`https://way2allah.com/pages/social.php`) is LIVE — a read-only GET
    confirmed the page chrome (document title, real `<h3 class="page-title">`
    heading — meaningful here, NOT the confirmed-empty $Author-null bug
    case — single self-referencing `href=""` breadcrumb item, the real
    `w2a_open_div()` portlet wrapper INCLUDING its empty `id=""` attribute,
    the `media/social-images/{file}` image path, the literal leading space
    on the واتساب link, and the `background-image` CSS rule) all matching
    this repo's source exactly — LIVE_RENDER_VERIFIED.
--}}
@section('title', 'روابط منصات السوشيال ميديا لشبكة الطريق إلى الله')

@push('styles')
    {{--
        pages/social.php:1-32's inline <style> block, restored in full —
        the previous version here was missing the `background-image` rule
        entirely (confirmed present in source and the live raw-path
        fetch). Moved into @push('styles') (a real <head> location) rather
        than reproducing its literal legacy placement (before the PHP tag
        even opens, i.e. before <!DOCTYPE html> itself) — a `<style>` tag
        applies identically regardless of which of these two valid-ish
        locations it sits in, so this is not a behavior change.
    --}}
    <style>
        .social-item:not(.free) {
            background-image: url(/assets/frontend/layout/css/images/block_bg.png);
            padding: 6px;
            margin-top: 15px;
        }
        .social-item.free {
            width: 100px;
            height: auto;
        }
        .social-item-content {
            background-color: #f1f1f1;
            padding: 5px;
            min-height: 42px;
            text-align: center;
            color: #153b61;
            font-weight: 600;
            line-height: 16px;
            font-size: 12px;
            display: grid;
            align-items: center;
        }
        .free-item {
            display: flex;
            align-items: center;
            min-height: 100px;
        }
        .free-item img {
            width: 100%;
            height: 100%;
        }
    </style>
@endpush

@section('content')
    {{--
        pages/social.php:38,42-43's chrome — `title($title)` (a real,
        meaningful heading, unlike khotab/day.php's/khotab/search.php's
        confirmed $Author-null bug) + `breadcrumb($breadcrumb)`, a single
        item with `'url'=>''` (isset() true — a real, if empty, href, same
        pattern already established for recite.htm/channels.htm). The
        shared breadcrumb() mechanism applies — <x-page-chrome> used.
    --}}
    <x-page-chrome
        heading="روابط منصات السوشيال ميديا لشبكة الطريق إلى الله"
        :breadcrumb="[['title' => 'روابط منصات السوشيال ميديا لشبكة الطريق إلى الله', 'url' => '']]"
    />

    {{--
        pages/social.php:46-427's real structure: social.php's OWN outer
        `.col-xs-12.col-sm-12.col-md-12.nopadding` wrapper (kept, already
        correct) contains `w2a_open_div()`'s OWN additional wrapper
        (`<div id="" class="col-md-12 col-sm-12">`, functions.php:100 —
        the empty `id=""` and the portlet-body's trailing-space class
        attribute, `<div class="portlet-body ">`, are real, confirmed,
        harmless legacy artifacts, reproduced exactly, same standard
        already applied to channel_fatawa.php's identical construct) —
        the previous version here had neither: just a bare
        `<div class="portlet-title">` with no `.portlet.box`/`.caption`/
        `.portlet-body` at all. `fa-telegram` is genuinely reused for 3
        different portlets (تليجرام/منصات تواصل متنوعة/تابعونا على
        بودكاست) — confirmed in source, not a copy-paste slip to "fix".
        Image path: `/media/social-images/{file}` (not the previous
        `/pages/social-images/{file}` — see SocialController's own
        updated docblock for the full evidence trail). `alt` now uses the
        restored, source-distinct `alt` field, not `name`.
    --}}
    <div class="row service-box margin-bottom-40 sh-w2a-block" id="social-media-content">
        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-facebook-square"></i> الفيس بوك</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($facebookPages as $page)
                                <div class="col-xs-6 col-sm-3 col-md-2">
                                    <div class="social-item">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-youtube-square"></i> اليوتيوب</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($youtubePages as $page)
                                <div class="col-xs-6 col-sm-3 col-md-2">
                                    <div class="social-item">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-instagram"></i> إنستجرام</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($instagramPages as $page)
                                <div class="col-xs-6 col-sm-3 col-md-2">
                                    <div class="social-item">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-telegram"></i> تليجرام</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($telegramPages as $page)
                                <div class="col-xs-6 col-sm-3 col-md-2">
                                    <div class="social-item">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-telegram"></i> منصات تواصل متنوعة</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($miscPages as $page)
                                <div class="col-xs-4 col-sm-2 col-md-2">
                                    <div class="social-item free">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <div class="free-item">
                                                <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            </div>
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-telegram"></i> تابعونا على بودكاست</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="row">
                            @foreach ($podcastPages as $page)
                                <div class="col-xs-6 col-sm-3 col-md-2">
                                    <div class="social-item free">
                                        <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                            <div class="free-item">
                                                <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}" style="width: 100%" alt="{{ $page['alt'] }}">
                                            </div>
                                            <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
