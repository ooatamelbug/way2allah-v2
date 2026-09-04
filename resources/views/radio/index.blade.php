@extends('layouts.app')

{{--
    radio.htm parity gap closure. `radio/index.php` (the file all 5 real
    `.htaccess` rules route to — see RadioController's docblock) registers
    `css/w2a_radio.css`, `css/custom.css` (register_css) and
    `assets/global/plugins/jquery-ui/jquery-ui.min.js`, `scripts/w2a_radio.js`
    (register_script) — all 4 confirmed present under the existing
    public/{css,scripts,assets} symlinks (ASSET_PRESENT). Previously loaded
    nowhere, so every selector in w2a_radio.css (`#w2a_radio`, `.player`,
    `.playlist`, `.controls`, `.tracker`, `.volume`, ...) had nothing to
    attach to (CONFIRMED_MARKUP_GAP, not just a missing asset) — the markup
    below restores the exact legacy structure those selectors target.
    `css/custom.css` here is the SAME file already pushed by
    anasheed/item.blade.php (distinct from the always-loaded
    /assets/frontend/layout/css/custom.css in layouts.app) — already
    established ASSET_EFFECTIVE elsewhere, reused as-is.
--}}
@push('styles')
    <link href="/css/w2a_radio.css" rel="stylesheet" type="text/css"/>
    <link href="/css/custom.css" rel="stylesheet" type="text/css"/>
@endpush

{{--
    scripts/w2a_radio.js (HTML5 `Audio`, not Flash/iframe) is
    ACTUALLY_EFFECTIVE for: play/pause/rew/fwd controls, the jQuery-UI
    tracker/volume sliders, playlist-item click-to-play, and the
    `!is_mobile` autoplay-on-load call — all of these target selectors
    (`.play`, `.pause`, `.rew`, `.fwd`, `.tracker`, `.volume`,
    `.playlist li`, `.player .title/.artist`, `.player-timer`,
    `.play-loading`) that now exist in the restored markup below.
    jquery-ui.min.js is a real, additive dependency — confirmed NOT already
    loaded by layouts.app (which only loads jquery core/migrate/fancybox/
    mockjax/autocomplete), and `.slider(...)` is called on `.tracker`/
    `.volume` unconditionally on page load.

    DEAD_LEGACY on this exact page (not reproduced, and correctly so —
    reproducing the markup they'd bind to would be inventing UI legacy
    itself never renders here): `.add-pl-item` / `add_to_playlist()` — the
    "add to playlist" sidebar buttons only exist in `radio/functions.php`'s
    `most_listen_media()`/`recent_video_html()`/`recent_audio_html()`,
    which `radio/index.php` never calls (it uses `topitems()` instead,
    confirmed by direct reading — see ContentSidebarWidget::topitems()).
    `.remove-item-playlist` — only rendered by `draw_radio_playlist()`
    (`radio/functions.php`), not `radio/index.php`'s own inline playlist
    `<li>` markup (line 124), which has no remove button.

    CONFIGURED_BUT_INERT: `save_last_listen()` / the `logged_in` personalized
    path — gated behind `jQuery("#w2a_l").length`, and `#w2a_l` does not
    exist anywhere in the current legacy source (grepped: header.php,
    footer.php, functions.php) — `logged_in` is unconditionally `false` at
    runtime today, in legacy's own current codebase, not only in Laravel.
--}}
@push('scripts')
    <script src="/assets/global/plugins/jquery-ui/jquery-ui.min.js" type="text/javascript"></script>
    <script src="/scripts/w2a_radio.js" type="text/javascript"></script>
@endpush

@section('title', 'راديو الطريق الى الله')

@section('content')
    {{-- Shared Page Chrome Parity Audit: radio/index.php:24-27 — single-item breadcrumb (current page, no `url` key at all — plain text, per breadcrumb_items()'s isset() check). --}}
    <x-page-chrome heading="راديو الطريق الى الله" :breadcrumb="[['title' => 'راديو الطريق الى الله']]" />

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-xs-12">
            <div class="w2a-radio-banner">
                <div class="w2a-radio-banner-content">
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 16px 0;">
                            <i class="fa fa-podcast" aria-hidden="true"></i>
                            راديو الطريق إلى الله المباشر
                        </h2>
                        <p style="font-size: 13px; opacity: 0.9; margin: 0;">استمع زائرنا الكريم بشكل متواصل لأحدث الدروس والمحاضرات الصوتية المضافة للموقع.</p>
                    </div>
                </div>
            </div>
        </div>

        @if($isMobile)
            <input type="hidden" name="w2a_is_mobile" id="w2a_is_mobile" value="true" />
        @endif

        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
            {{-- radio/index.php:51-130 — #w2a_radio wraps ONLY the player+playlist, not the sidebar (w2a_radio.css's `#w2a_radio{direction:ltr}` would otherwise flip the Arabic sidebar to LTR too). --}}
            <div id="w2a_radio">
                <div class="w2a-radio-card">
                    <div class="player">
                        <div class="play-loading"><i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>

                        <div class="w2a-radio-player-top">
                            <div class="w2a-radio-disc-wrap">
                                <i class="fa fa-music w2a-radio-disc-icon" aria-hidden="true"></i>
                            </div>
                            <div class="w2a-radio-track-info">
                                <h3 class="title">جاري تحميل البث...</h3>
                                <p class="artist">راديو الطريق إلى الله</p>
                            </div>
                        </div>

                        <div class="w2a-radio-tracker-wrap">
                            <div class="tracker"></div>
                            <div class="player-timer"><span class="current-t">00:00:00</span> / <span class="total-t">00:00:00</span></div>
                        </div>

                        <div class="w2a-radio-controls-bar">
                            <div class="controls">
                                <div class="play" title="تشغيل"></div>
                                <div class="pause" title="إيقاف مؤقت"></div>
                                <div class="rew" title="السابق"></div>
                                <div class="fwd" title="التالي"></div>
                            </div>
                            <div class="volume-cont">
                                <div class="vol-icon" title="مستوى الصوت"></div>
                                <div class="volume"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <ul class="playlist">
                        @foreach ($playlist as $item)
                            @php($authorName = trim($item->prename.' '.$item->author_name))
                            <li audiourl="{{ $item->audio_url }}" cover="cover1.jpg" artist="{{ $authorName }}" id="li_{{ $item->khid }}_{{ $item->pl_section }}">
                                <span style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fa fa-play-circle" style="opacity: 0.7;" aria-hidden="true"></i>
                                    {{ $item->title }}
                                </span>
                                <small style="opacity: 0.75; font-weight: 600;">{{ $authorName }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <aside class="col-xs-12 col-sm-4 col-md-4 col-lg-4 nopadding">
            {{-- radio/index.php:133-141 — topitems('hits', "vedio=1", "time DESC", 10): mode='hits' displays a download count, NOT the date, despite ordering by time. Same media-list/portlet convention as khotab/day.blade.php's sidebar. --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-video-camera"></i> جديد المواد المرئية</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$newestVideo" />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-headphones"></i> جديد المواد الصوتية</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$newestAudio" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
