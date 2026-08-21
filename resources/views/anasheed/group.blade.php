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
                        <div class="row telawat_authors_list">
                            @foreach ($subGroups as $subGroup)
                                @php($comment = empty($subGroup->des) ? 'بدون تعليق' : $subGroup->des)
                                <div class="col-xs-12 col-sm-6 col-md-4 col-lg-3 telawah-author">
                                    <div class="thumbnail">
                                        <div class="telawa-author-name text-center"><a href="/var-group-{{ $subGroup->id }}.htm"><span>{{ $subGroup->title }}</span></a></div>
                                        <div class="row">
                                            <div class="col-xs-12 col-sm-5 col-md-5 telawa-thumb"><a href="/var-group-{{ $subGroup->id }}.htm"><img src="{{ $subGroup->thumbUrl() }}" alt="{{ $subGroup->title }}"></a></div>
                                            <div class="col-xs-12 col-sm-7 col-md-7 telawa-details">
                                                <div class="list-group">
                                                    <div class="list-group-item telawah-group-subcats"> الأقسام الفرعية : {{ (int) $subGroup->child }} قسم </div>
                                                    <div class="list-group-item telawah-group-recits"> المقاطع : {{ (int) $subGroup->anasheed }} مقطع </div>
                                                    <div class="list-group-item telawah-group-visits"> الزيارات : {{ (int) $subGroup->hits }} زيارة </div>
                                                    <div class="list-group-item telawah-group-comment"> التعليق : {{ $comment }} </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                        <div class="row var_group_download">
                            <div class="col-lg-2 col-md-3 col-sm-3 col-xs-6 text-center">
                                <a href="/var-series-{{ $groupModel->id }}.grx">
                                    <i class="fa fa-save"></i><br>رابط تحميل السلسلة
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-3 col-xs-6 text-center">
                                <a href="http://download.getright.com/getright-download.exe">
                                    <img border="0" src="http://way2allah.com/images/admin/icons/getright.png" alt="getright">
                                    <br>رابط تحميل برنامج التحميل
                                </a>
                            </div>
                            <div class="col-lg-8 col-md-6 col-sm-6 col-xs-12 download-notes">
                                قبل تحميل السلسلة يجب أن يكون برنامج (getright) مثبت على جهازك وسوف يقوم بتحميل السلسلة مباشرة بمجرد الضغط على تحميل السلسلة. لتحميل البرنامج إضغط على رابط تحميل البرنامج
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
                        {{-- G-13-09 (media/visual parity phase): anasheed/functions.php:326-340's
                             list_anasheed() — a raw (non-thumbnails.php) frame image per item,
                             no file_exists() gate (confirmed dead fallback check in source).
                             .hover-var-item is genuinely PHP-comment-wrapped in source
                             (functions.php:343-346) — never reachable, not reproduced. --}}
                        <div class="row var_group_items_list">
                            @forelse ($items as $item)
                                <div class="col-lg-2 col-md-2 col-sm-3 col-xs-6 var_group_item">
                                    <div class="center-block">
                                        <a href="/var-item-{{ $item->id }}.htm">
                                            <img src="{{ $item->frameThumbUrl() }}" class="img-responsive" alt="{{ $item->title }}" height="67">
                                        </a>
                                    </div>
                                    <div class="text-center">
                                        <a href="/var-item-{{ $item->id }}.htm"><h4>{{ $item->title }}</h4></a>
                                    </div>
                                </div>
                            @empty
                                {{-- functions.php:365-367 — list_anasheed()'s own `if(!empty($items))`
                                     check is on the CURRENT paginated page, separate from group.php's
                                     outer count>0 gate above — reachable on a beyond-the-last-page
                                     request (e.g. /var-group-{id}-page-999.htm), not dead code. --}}
                                <div class="col-md-12"><div class="text-center alert alert-danger">عفوا ، لا يوجد مواد مضافة في هذا القسم</div></div>
                            @endforelse
                        </div>
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
