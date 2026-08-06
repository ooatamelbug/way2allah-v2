@extends('layouts.app')

{{-- Content, structure, and inline CSS copied verbatim from legacy
     pages/social.php. One deliberate deviation from "verbatim": image src
     is `/pages/social-images/{file}` here, not the legacy markup's
     `media/social-images/{file}` — that legacy path points at a directory
     that does not exist (confirmed). The real assets live in the legacy
     `pages/social-images/` directory, and Blueprint §12 names that exact
     path as the fix target ("same relative paths preserved") — NOT
     `/images/social-images/`, which an earlier pass of this file used by
     over-generalizing from `khotab/item.blade.php`'s `/images/flags/`
     (itself a real subfolder of `images/`, unlike `pages/social-images/`).
     Corrected per docs/reviews/post-gap-closure-consistency-review.md
     Finding 1. This is a bug fix, not a behavior change — every image on
     this page was broken in production regardless of which wrong path was
     used. --}}

@section('title', 'روابط منصات السوشيال ميديا لشبكة الطريق إلى الله')

@section('content')
    <style>
        .social-item:not(.free) {
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

    <div class="row service-box margin-bottom-40 sh-w2a-block" id="social-media-content">
        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-facebook-square"></i> الفيس بوك</div>
            <div class="row">
                @foreach ($facebookPages as $page)
                    <div class="col-xs-6 col-sm-3 col-md-2">
                        <div class="social-item">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-youtube-square"></i> اليوتيوب</div>
            <div class="row">
                @foreach ($youtubePages as $page)
                    <div class="col-xs-6 col-sm-3 col-md-2">
                        <div class="social-item">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-instagram"></i> إنستجرام</div>
            <div class="row">
                @foreach ($instagramPages as $page)
                    <div class="col-xs-6 col-sm-3 col-md-2">
                        <div class="social-item">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-telegram"></i> تليجرام</div>
            <div class="row">
                @foreach ($telegramPages as $page)
                    <div class="col-xs-6 col-sm-3 col-md-2">
                        <div class="social-item">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-telegram"></i> منصات تواصل متنوعة</div>
            <div class="row">
                @foreach ($miscPages as $page)
                    <div class="col-xs-4 col-sm-2 col-md-2">
                        <div class="social-item free">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <div class="free-item">
                                    <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                </div>
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 nopadding">
            <div class="portlet-title"><i class="fa fa-telegram"></i> تابعونا على بودكاست</div>
            <div class="row">
                @foreach ($podcastPages as $page)
                    <div class="col-xs-6 col-sm-3 col-md-2">
                        <div class="social-item free">
                            <a href="{{ $page['link'] }}" title="{{ $page['name'] }}" target="_blank">
                                <div class="free-item">
                                    <img src="/pages/social-images/{{ $page['image'] }}" style="width: 100%" alt="{{ $page['name'] }}">
                                </div>
                                <div class="social-item-content"><span>{{ $page['name'] }}</span></div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
