@extends('layouts.app')

@section('title', $groupModel->title)

{{--
    var-group-{id}.htm parity: anasheed/group.php:5 — register_css('css/custom.css'),
    unconditional. Resolves to https://way2allah.com/css/custom.css (register_css()'s
    default branch, new_functions.php:75-87) — a distinct, root-level stylesheet
    from the sitewide assets/frontend/layout/css/custom.css already loaded in the
    shared layout. Confirmed genuinely necessary (not redundant, not irrelevant):
    css/custom.css defines real rules for .telawah-author/.telawa-thumb/.telawa-details
    (list_sub_groups()'s card grid), .var_group_items_list .var_group_item/.center-block
    (list_anasheed()'s item grid — but NOT .hover-var-item, whose markup is genuinely
    PHP-comment-wrapped in list_anasheed(), functions.php:343-346 — that specific
    selector is ASSET_LOADED_BUT_IRRELEVANT, never reachable), and .var_group_download
    (the GetRight block). None of these selectors existed anywhere in this view's
    prior markup (plain <ul>/<li>/bare <div>), so the stylesheet alone would have
    been ASSET_PRESENT but not ASSET_EFFECTIVE — restored together with the real
    markup below, not as a CSS-only change.
--}}
@push('styles')
    <link href="/css/custom.css" rel="stylesheet" type="text/css"/>
@endpush

@section('content')
    {{-- Shared Page Chrome Parity Audit: group.php:22-26's heading + ancestor breadcrumb, restored via AnasheedGroup::breadcrumbTrail() (already used by var-item-{id}.htm's own implementation, not modified here). --}}
    <x-page-chrome :heading="$groupModel->title" :breadcrumb="$breadcrumbTrail" />

    <div class="row service-box margin-bottom-40">
        {{--
            group.php:50-59 — conditional on $Group->child (has sub-groups),
            list_sub_groups() (functions.php:188-236): real .telawah-author
            card grid, NOT a plain list. Data unchanged — AnasheedGroup::
            thumbUrl() (G-13-04) already reproduces the icon/pix001.gif
            branch exactly; only the wrapping markup was wrong before.
        --}}
        @if($subGroups->isNotEmpty())
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-sitemap"></i> قائمة الأقسام الفرعية: {{ $groupModel->title }}</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.anasheed-subgroup-grid :groups="$subGroups" />
                    </div>
                </div>
            </div>
        @endif

        {{--
            group.php:60-78 — both the GetRight-download portlet and the
            items-list portlet share ONE gate: count_anasheed_for_group($Group) > 0.
            $items->total() (the paginator's pre-LIMIT count) is the same
            quantity already computed by AnasheedItem::inGroup() — no new
            query needed. (count_anasheed_for_group() itself has no group
            98/16 special case, unlike list_anasheed()/inGroup() — a narrow,
            pre-existing legacy inconsistency only observable for group 98,
            already a documented special case elsewhere; not re-derived
            with a second query just to replicate that one edge exactly.)
        --}}
        @if($items->total() > 0)
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-download"></i> تحميل سلسلة : {{ $groupModel->title }}</div>
                    </div>
                    <div class="portlet-body">
                        <div class="w2a-series-download-banner">
                            <div class="w2a-series-download-info">
                                <div class="w2a-series-download-icon" aria-hidden="true"><i class="fa fa-cloud-download"></i></div>
                                <div class="w2a-series-download-text">
                                    <h4>تحميل السلسلة بالكامل دفعة واحدة</h4>
                                    <p>حمّل ملف السلسلة (.grx) لاستخدامه عبر برنامج GetRight أو برامج التحميل المتوافقة.</p>
                                </div>
                            </div>
                            <div class="w2a-series-download-actions">
                                <a href="/var-series-{{ $groupModel->id }}.grx" class="w2a-series-download-btn"><i class="fa fa-download" aria-hidden="true"></i> <span>تحميل السلسلة (.grx)</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-video-camera"></i> قائمة المواد : {{ $groupModel->title }}</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.anasheed-media-grid :items="$items" />
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        @endif

        {{--
            G-13 closure (Anasheed Group Sidebar parity fix): group.php
            (full file read) never calls most_downloaded_recent_sidebar()
            — unlike anasheed/item.blade.php, this page has no "most
            downloaded"/"most recent" sidebar in legacy. Removed.
        --}}

        @if(!empty($groupModel->description))
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-body">
                        <p>{{ $groupModel->description }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
