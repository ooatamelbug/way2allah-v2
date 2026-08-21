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
        {{-- radio/index.php:33-36 — sits outside both columns, exactly as legacy renders it (not "fixed" into the grid). --}}
        <div class="current-user-warning">
            <div class="alert alert-info" style="text-align:right;">يمكنك الإستماع زائرنا الكريم إلى أحدث الدروس المضافة إلى موقعنا على هيئة صوتيات
            </div>
        </div>

        @if($isMobile)
            <input type="hidden" name="w2a_is_mobile" id="w2a_is_mobile" value="true" />
        @endif

        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 nopadding">
            {{-- radio/index.php:51-130 — #w2a_radio wraps ONLY the player+playlist, not the sidebar (w2a_radio.css's `#w2a_radio{direction:ltr}` would otherwise flip the Arabic sidebar to LTR too). --}}
            <div id="w2a_radio">
                <div class="player">
                    <div class="play-loading"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i></div>
                    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                        <div class="col-lg-10 col-md-9 col-sm-8 col-xs-9">
                            <div class="title"></div>
                            <div class="artist"></div>
                            <div class="clearfix"></div>
                            <div class="row">
                                <div class="col-lg-9 col-md-8 col-sm-12 col-xs-12">
                                    <div class="tracker"></div>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-12 col-xs-12">
                                    <div class="player-timer"><span class="current-t">00:00:00</span> / <span class="total-t">00:00:00</span></div>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            <div class="row">
                                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-6 pull-right controls-cont">
                                    <div class="controls">
                                        <div class="play"></div>
                                        <div class="pause"></div>
                                        <div class="rew"></div>
                                        <div class="fwd"></div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 volume-cont  pull-right">
                                    <div class="vol-icon"></div>
                                    <div class="volume"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- radio/index.php:89-129's w2a_open_div()/w2a_close_div() portlet, matching the same shape day.blade.php's sidebar boxes already use. --}}
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-eject"></i> قائمة التشغيل الحالية</div>
                        </div>
                        <div class="portlet-body">
                            <ul class="playlist">
                                @foreach ($playlist as $item)
                                    <li audiourl="{{ $item->audio_url }}" cover="cover1.jpg" artist="{{ trim($item->prename.' '.$item->author_name) }}" id="li_{{ $item->khid }}_{{ $item->pl_section }}">{{ $item->title }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
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
                        <ul class="media-list">
                            @foreach ($newestVideo as $item)
                                <li class="media">
                                    <a class="pull-left" href="javascript:;"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
                                        <small>عدد مرات التحميل: {{ number_format($item->hits) }} مرة</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-headphones"></i> جديد المواد الصوتية</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="media-list">
                            @foreach ($newestAudio as $item)
                                <li class="media">
                                    <a class="pull-left" href="javascript:;"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
                                        <small>عدد مرات التحميل: {{ number_format($item->hits) }} مرة</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
