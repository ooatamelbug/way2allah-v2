@extends('layouts.app')

{{--
    `cds-main.htm` Legacy-Source Design Reconstruction. Route trace:
    `.htaccess:251` (`cds-main.htm -> new_modules.php?name=w2acd`, no
    `op=`) confirms the missing `new_modules.php` dispatcher — the pretty
    URL 404s in production (LEGACY_PRETTY_URL_ORPHANED), but the raw
    handler `w2acd/cds.php` is LIVE (confirmed via a read-only GET) and
    was used as the structural reference, matching source exactly
    (LIVE_RENDER_VERIFIED — no drift found).

    `cds.php` (43 lines, read in full) is a SINGLE full-width column —
    confirmed live: `.row service-box margin-bottom-40` directly contains
    one `.col-md-12.col-sm-12` portlet, no `.col-lg-9`/`.col-lg-3` split
    anywhere. The "الأكثر تحميلا"/"احدث المواد" sidebar previously
    rendered here does NOT belong on this page — it's `w2acd/item.php`'s
    own `most_downloaded_recent_sidebar()` (functions.php:243-246),
    confirmed absent from `cds.php`'s own render path by a full,
    independent re-read. Removed from this view only — `w2acd/show.blade.php`
    (the separate `cds-item-{id}.htm` detail page) and
    `W2acdController::index()`'s own data-fetching are untouched, in case
    a future `cds-group-{id}.htm` route needs them.

    `<title>` uses a SINGLE sitename suffix here — a real, confirmed
    difference from every other page in this migration:
    `cds.php:5-7` never pre-concatenates `' - '.$sitename` into
    `$header['title']` (unlike category.php/group.php/chat_rooms.php,
    which all do) — only the shared layout's own single, unconditional
    append applies. Confirmed live:
    `<title>قسم الاسطوانات الدعوية - الطريق إلى الله</title>` (one
    suffix, not two).

    Pagination: `w2acd/functions.php:45-83`'s own custom pager
    (`cds-group-0-page-{N}.htm` links, a 5-window "الأولى/&lt;&lt;/&gt;&gt;/الأخيرة"
    widget, rendered twice — before AND after the grid) is
    CONFIRMED_MARKUP_GAP but deliberately NOT reproduced here, matching
    this project's own already-established precedent for this exact
    class of "distinct, substantial UI mechanism" (fatawa-channel-{id}.htm's
    identical custom pager, also deferred). Laravel's native `->links()`
    preserves the underlying paging capability without inventing a new
    design.
--}}
@section('title', 'قسم الاسطوانات الدعوية')

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush

@section('content')
    <x-page-chrome
        heading="قائمة الإسطوانات الدعوية"
        :breadcrumb="[['title' => 'الاسطوانات الدعوية', 'url' => '']]"
    />

    <div class="row service-box margin-bottom-40">
        <div id="" class="col-md-12 col-sm-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-child"></i> قائمة الإسطوانات العامة</div>
                </div>
                <div class="portlet-body ">
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <div class="row">
                            @foreach ($items as $item)
                                @php
                                    $listPhoto = $item->firstThumbnailFilename()
                                        ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                                        : '/images/way2_cddefault.png';
                                    $listThumb = \App\Domain\Content\Support\MediaUrl::thumbnail('h=104&w=105&src='.$listPhoto);
                                @endphp
                                <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6 text-center">
                                    <div class="var_item cd_bg_class">
                                        <a href="/cds-item-{{ $item->id }}.htm" class="cd_bg_img">
                                            <img src="{{ $listThumb }}" alt="إضغط لمشاهدة ''&nbsp;{{ $item->title }}> ''">
                                            <br/><span>{{ $item->title }}</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
