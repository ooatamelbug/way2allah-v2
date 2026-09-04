@extends('layouts.app')

{{--
    `gallery.htm` Full Design Parity Pass. `gallery/list.php` re-read in
    full: shared title()/breadcrumb() chrome (<x-page-chrome> applies —
    the malformed "gift" icon is a confirmed dead artifact, not
    reproduced), an extra outer <div class="row"> wrapping the real
    w2a_open_div() portlet (list.php:32-33), and a `.album-item` card
    whose exact structure was previously simplified: the title link is a
    SEPARATE `<a class="w2a_album_title">` around the `<h5>` (not nested
    inside the thumbnail's own `<a>`), the thumbnail `<a>` carries
    `class="standard"`, the date/count spans carry their real classes and
    icons, and the conditional "حفظ الألبوم" button (`is_compressed==1`,
    confirmed live — 28 of 84 albums on production) was missing entirely
    despite `is_compressed` already being selected by the query.

    No page-specific CSS is registered anywhere in `list.php` (no
    `register_css()` call at all, unlike chat_room.htm/category.php) —
    the task's own "gallery.css exists on the channels page" hint does
    NOT apply here, confirmed by direct source read and a live asset-tag
    grep against production (zero gallery-css-named links). `fancybox` is
    the standard sitewide bundle already loaded by the shared layout —
    CONFIGURED_BUT_INERT here specifically: no `data-rel`, no fancybox
    init call anywhere in this page's own markup.
--}}
@section('title', 'التصميمات الدعوية - '.config('app.name'))

@section('content')
    <x-page-chrome heading="التصميمات الدعوية" :breadcrumb="[['title' => 'التصميمات الدعوية']]" />

    <div class="row service-box margin-bottom-40">
        <div class="col-xs-12">
            {{--
                list.php:23,32-33,63-66: the whole block — including the
                extra outer .row and the portlet wrapper itself — only
                renders when !empty($albums); no empty-state markup exists
                in source for this page.
            --}}
            @if ($albums->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-picture-o" aria-hidden="true"></i> التصميمات والبطاقات الدعوية</div>
                        </div>
                        <div class="portlet-body ">
                            <div class="w2a-gallery-wrap">
                                <div class="w2a-gallery-toolbar">
                                    <div class="w2a-gallery-search-wrap">
                                        <i class="fa fa-search w2a-gallery-search-icon" aria-hidden="true"></i>
                                        <label class="sr-only" for="w2a_gallery_search_input">ابحث في الألبومات والبطاقات الدعوية</label>
                                        <input type="search" id="w2a_gallery_search_input" class="w2a-gallery-search-input" placeholder="ابحث في الألبومات والبطاقات الدعوية..." autocomplete="off">
                                        <button type="button" id="w2a_gallery_search_clear" class="w2a-gallery-search-clear" hidden aria-label="مسح البحث">
                                            <i class="fa fa-times" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="w2a-tree-badge">
                                        <i class="fa fa-picture-o" aria-hidden="true"></i> {{ $albums->count() }} ألبوم دعوي
                                    </div>
                                </div>

                                <div class="w2a-albums-grid">
                                    @foreach ($albums as $album)
                                        @php($thumbUrl = \App\Domain\Content\Support\MediaUrl::thumbnail('h=250&w=350&src='.($thumbnailUrls[$album->album_id] ?? null)))
                                        <article class="w2a-album-card" data-title="{{ $album->title }}">
                                            <div class="w2a-album-cover-wrap">
                                                <a href="/gallery-{{ $album->album_id }}.htm">
                                                    <img src="{{ $thumbUrl }}" alt="{{ $album->title }}" class="w2a-album-cover" width="350" height="250" loading="lazy" decoding="async">
                                                    <span class="w2a-album-overlay" aria-hidden="true">
                                                        <span class="w2a-album-overlay-icon"><i class="fa fa-search-plus"></i></span>
                                                    </span>
                                                </a>
                                                <span class="w2a-album-count-badge"><i class="fa fa-photo" aria-hidden="true"></i> {{ $album->count }} صورة</span>
                                            </div>

                                            <div class="w2a-album-body">
                                                <a href="/gallery-{{ $album->album_id }}.htm" style="text-decoration: none;">
                                                    <h3 class="w2a-album-card-title">{{ $album->title }}</h3>
                                                </a>
                                                <div class="w2a-album-meta">
                                                    <span class="w2a-album-date"><i class="fa fa-calendar" aria-hidden="true"></i> تحديث: {{ $album->last_update ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $album->last_update) : '' }}</span>
                                                </div>
                                                <div class="w2a-album-actions">
                                                    <a href="/gallery-{{ $album->album_id }}.htm" class="w2a-album-btn-view">
                                                        <span>تصفح الألبوم</span>
                                                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                                                    </a>
                                                    @if ($album->is_compressed == 1)
                                                        <a href="javascript:void(0)" onclick="downlaod_gellery_images({{ $album->album_id }})" class="w2a-album-btn-download" title="تحميل جميع صور الألبوم">
                                                            <i class="fa fa-download" aria-hidden="true"></i>
                                                            <span>تحميل</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                                <p id="w2a_gallery_result_status" class="sr-only" aria-live="polite"></p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{--
        list.php:69-83's inline downlaod_gellery_images() — reproduced as
        real, present markup (it IS what the "حفظ الألبوم" button calls),
        but its actual destination (`download-album-{id}.htm`) routes
        through the confirmed-missing `new_modules.php` dispatcher
        (.htaccess:367-369, IF-026) — already correctly left unbuilt by
        GalleryController's own docblock (IF-027). Not implemented here
        either: reproducing a broken-in-production trigger is not the
        same as building the endpoint it calls.
    --}}
    @push('scripts')
        <script>
            function downlaod_gellery_images(id){
                var ajax_url = "download-album-"+id+".htm";
                $.ajax({
                    url:ajax_url,
                    type:"GET",
                    success:function(data){
                        window.location = data;
                    }
                });
            }
        </script>
    @endpush
@endsection
