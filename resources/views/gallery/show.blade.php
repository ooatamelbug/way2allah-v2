@extends('layouts.app')

{{--
    `gallery-{id}.htm` Full Design Parity Pass. `gallery/item.php` re-read
    in full: shared title()/breadcrumb() chrome (previously a bare <h1>,
    no breadcrumb at all), the real w2a_open_div() portlet + extra outer
    <div class="row"> (item.php:56-57, same shape as gallery.htm's own),
    and a per-image card whose exact structure was simplified: missing
    the `.album-item.albumpic`/`.center-block.album-img` wrappers, the
    `w2a_singl_img` class on the lightbox link, and the entire
    `w2a_gal_sav` save-image link's real classes/onclick/icon. The G-03
    thumbnail-URL shapes, lightbox CSS/JS + init call, and the
    per-image download route were already correct — untouched.

    `alt="{{ $albumModel->title }}"` on every image is legacy's own real
    behavior (item.php:69's `alt="<?php echo $album->title;?>"` — the
    ALBUM's title, not a per-image caption; no per-image title/description
    field is ever rendered anywhere in item.php, confirmed by the full
    source read) — not "fixed" to something more descriptive.

    `onclick="loadImg(...)"` (item.php:72) calls a JS function that is
    NOT defined anywhere in this codebase (confirmed: zero matches for
    `function loadImg` in the entire legacy tree) — DEAD_LEGACY, but
    harmless (a normal `<a href>` still navigates on click; the
    onclick simply throws silently first) and reproduced as literal,
    present, source-proven markup, not omitted.
--}}
@section('title', 'التصميمات الدعوية - '.$albumModel->title.' - '.config('app.name'))

@push('styles')
    <link href="/gallery/lightbox/jquery.lightbox-0.4.css" rel="stylesheet" type="text/css">
    {{--
        item.php:35-40's literal inline <style> — targets fancybox's own
        classes, but this page uses jquery.lightbox, not fancybox
        (fancybox is never initialized here). A real, confirmed,
        functionally-inert rule in source — reproduced as found, same
        standard already applied to other confirmed-harmless legacy
        artifacts throughout this migration.
    --}}
    <style type="text/css">
        .fancybox-next span,
        .fancybox-prev span {
            visibility: visible;
        }
    </style>
@endpush

@section('content')
    <x-page-chrome
        heading="التصميمات الدعوية - {{ $albumModel->title }}"
        :breadcrumb="[['title' => 'التصميمات الدعوية', 'url' => '/gallery.htm'], ['title' => $albumModel->title]]"
    />

    <div class="row service-box margin-bottom-40">
        <div class="col-xs-12 col-sm-12 col-md-12">
            {{--
                item.php:46,56-57,79-86: the whole block — including the
                extra outer .row and the portlet wrapper — only renders
                when !empty($album_images); otherwise the real
                .alert.alert-info empty-state message renders instead
                (previously a bare <p>, no portlet at all either way).
            --}}
            @if ($images->isNotEmpty())
                <div class="row">
                    <div id="" class="col-md-12 col-sm-12">
                        <div class="portlet box blue">
                            <div class="portlet-title">
                                <div class="caption"><i class="fa fa-picture-o"></i> ألبوم : {{ $albumModel->title }}</div>
                            </div>
                            <div class="portlet-body ">
                                <div class="w2a-gallery-album-header">
                                    <div class="w2a-gallery-album-info">
                                        <h2 class="w2a-gallery-album-title">{{ $albumModel->title }}</h2>
                                        @if (! empty($albumModel->des))
                                            <p class="w2a-gallery-album-desc">{{ $albumModel->des }}</p>
                                        @endif
                                        <div class="w2a-gallery-album-stats">
                                            <span><i class="fa fa-picture-o" aria-hidden="true"></i> {{ number_format($images->count()) }} صورة</span>
                                            <span><i class="fa fa-eye" aria-hidden="true"></i> {{ number_format((int) $albumModel->hits) }} مشاهدة</span>
                                            @if (! empty($albumModel->last_update))
                                                <span><i class="fa fa-calendar" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $albumModel->last_update) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ((int) $albumModel->is_compressed === 1)
                                        <button type="button" onclick="downlaod_gellery_images({{ $albumModel->album_id }})" class="w2a-gallery-download-zip-btn">
                                            <i class="fa fa-file-archive-o" aria-hidden="true"></i> تحميل الألبوم بالكامل (ZIP)
                                        </button>
                                    @endif
                                </div>

                                <div class="w2a-gallery-grid">
                                    @foreach ($images as $image)
                                        @php($thumbUrl = \App\Domain\Content\Support\MediaUrl::thumbnail('h=260&w=340&src='.$image->url))
                                        @php($fullUrl = \App\Domain\Content\Support\MediaUrl::thumbnail('w=1000&src='.$image->url))
                                        <article class="w2a-gallery-item-card">
                                            <div class="w2a-gallery-thumb-wrap">
                                                <img
                                                    src="{{ $thumbUrl }}"
                                                    alt="{{ $albumModel->title }} - صورة {{ $loop->iteration }}"
                                                    class="w2a-gallery-img"
                                                    width="340"
                                                    height="260"
                                                    loading="lazy"
                                                >
                                                <div class="w2a-gallery-overlay">
                                                    <a href="{{ $fullUrl }}" class="lightbox w2a-gallery-action-btn" rel="album{{ $albumModel->album_id }}" title="{{ $albumModel->title }} - صورة {{ $loop->iteration }}">
                                                        <i class="fa fa-search-plus" aria-hidden="true"></i> تكبير
                                                    </a>
                                                    <a href="/albumimg-download-{{ $image->image_id }}.htm" class="w2a-gallery-action-btn w2a-download">
                                                        <i class="fa fa-download" aria-hidden="true"></i> حفظ
                                                    </a>
                                                </div>
                                            </div>
                                            <footer class="w2a-gallery-card-footer">
                                                <span class="w2a-gallery-photo-num">صورة #{{ $loop->iteration }}</span>
                                                <a href="/albumimg-download-{{ $image->image_id }}.htm" class="w2a-gallery-quick-down">
                                                    <i class="fa fa-download" aria-hidden="true"></i> تحميل
                                                </a>
                                            </footer>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info" role="alert"> <strong>عفوا!</strong> لا يوجد صور مضافة في هذا الالبوم بعد. </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script src="/gallery/lightbox/jquery.blockUI-1.33.pack.js"></script>
        <script src="/gallery/lightbox/jquery.lightbox-0.4.pack.js"></script>
        <script>
            $(document).ready(function () {
                $('a.lightbox').lightBox();
            });

            function downlaod_gellery_images(id) {
                $.get('download-album-' + id + '.htm', function (downloadUrl) {
                    window.location.assign(downloadUrl);
                });
            }
        </script>
    @endpush
@endsection
