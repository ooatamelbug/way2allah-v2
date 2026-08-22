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
        <div class="col-xs-12 col-sm-12 col-md-12">
            {{--
                list.php:23,32-33,63-66: the whole block — including the
                extra outer .row and the portlet wrapper itself — only
                renders when !empty($albums); no empty-state markup exists
                in source for this page.
            --}}
            @if ($albums->isNotEmpty())
                <div class="row">
                    <div id="" class="col-md-12 col-sm-12">
                        <div class="portlet box blue">
                            <div class="portlet-title">
                                <div class="caption"><i class="fa fa-picture-o"></i> التصميمات الدعوية</div>
                            </div>
                            <div class="portlet-body ">
                                <div class="row albums_list row-fluid">
                                    @foreach ($albums as $album)
                                        @php($thumbUrl = '/thumbnails.php?h=250&w=350&src='.optional($album->thumbnailImage())->url)
                                        <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                            <div class="album-item">
                                                <a class="standard" href="/gallery-{{ $album->album_id }}.htm"><img src="{{ $thumbUrl }}" alt="{{ $album->title }}" class="img-responsive"></a>
                                                <a class="w2a_album_title" href="/gallery-{{ $album->album_id }}.htm">
                                                    <h5 class="text-center">{{ $album->title }}</h5>
                                                </a>
                                                <span class="album_last_update"><i class="fa fa-calendar"></i> اخر تحديث : {{ $album->last_update ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $album->last_update) : '' }}</span>
                                                <span class="w2a_gallery_imgs_c"><i class="fa fa-files-o"></i> {{ $album->count }} صورة</span>
                                                @if ($album->is_compressed == 1)
                                                    <a href="Javascript:void(0)" onclick="downlaod_gellery_images({{ $album->album_id }})" class="w2a_album_save" title="حفظ جميع صور الألبوم : {{ $album->title }}"><i></i>حفظ الألبوم</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
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
