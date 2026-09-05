@extends('layouts.app')

{{-- Premium social directory built from the existing controller collections. --}}
@section('title', 'روابط منصات السوشيال ميديا لشبكة الطريق إلى الله')

@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
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

    <div class="w2a-refresh-page w2a-social-page" id="social-media-content">
        <div class="w2a-social-intro">
            <span class="w2a-social-intro__icon" aria-hidden="true"><i class="fa fa-share-alt"></i></span>
            <div>
                <strong>كل منصات الطريق إلى الله في مكان واحد</strong>
                <p>اختر منصتك المفضلة وتابع أحدث المواد والبرامج والمقاطع.</p>
            </div>
        </div>

        <x-content.social-platform-section title="الفيس بوك" icon="fa-facebook-square" :items="$facebookPages" />
        <x-content.social-platform-section title="اليوتيوب" icon="fa-youtube-play" :items="$youtubePages" />
        <x-content.social-platform-section title="إنستجرام" icon="fa-instagram" :items="$instagramPages" />
        <x-content.social-platform-section title="تليجرام" icon="fa-paper-plane" :items="$telegramPages" />
        <x-content.social-platform-section title="منصات تواصل متنوعة" icon="fa-globe" :items="$miscPages" variant="compact" />
        <x-content.social-platform-section title="تابعونا على بودكاست" icon="fa-microphone" :items="$podcastPages" variant="compact" />
    </div>
@endsection
